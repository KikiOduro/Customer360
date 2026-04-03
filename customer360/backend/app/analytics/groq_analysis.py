"""
Groq narrative generation for Customer360.

This module is intentionally non-blocking: if the API key is missing, a rate limit
is exceeded, or the upstream Groq request fails, the segmentation pipeline still
finishes and the dashboard/report fall back to the deterministic story summary.
"""
from __future__ import annotations

import json
import logging
import time
from datetime import datetime, timedelta
from typing import Any, Dict, Optional, Tuple

from sqlalchemy import func
from sqlalchemy.orm import Session

from ..config import (
    GROQ_API_KEY,
    GROQ_DAILY_REQUEST_LIMIT,
    GROQ_ENABLED,
    GROQ_MAX_INPUT_CHARS,
    GROQ_MAX_OUTPUT_TOKENS,
    GROQ_MODEL,
    GROQ_REQUESTS_PER_MINUTE,
    GROQ_RETRY_ATTEMPTS,
    GROQ_USER_COOLDOWN_SECONDS,
    GROQ_USER_DAILY_LIMIT,
)
from ..models import Job

logger = logging.getLogger(__name__)


SYSTEM_PROMPT = """
You are a trusted business advisor helping small and medium business owners in Ghana
understand their customer data. Use warm, clear, everyday English that a non-technical
business owner can read quickly.

Rules:
1. Do not use technical jargon such as RFM, clustering, SHAP, PCA, K-means, model,
   algorithm, or silhouette.
2. Use only the numbers provided in the data. Never invent metrics.
3. Use GHS for currency.
4. Keep each section concise and practical.
5. Recommend actions a Ghana SME can do this week, such as WhatsApp follow-up,
   phone calls, loyalty offers, or personal check-ins.
6. Never include raw customer IDs, names, phone numbers, or emails.
7. Use the customer group names exactly as they appear in the user data below.
   Do not rename any segment, do not remove " - Group N" suffixes, and return one
   segment_insights entry for each exact group name provided.
8. Return only valid JSON with this exact shape:
{
  "headline": "short one-sentence business summary",
  "story": "3-4 sentence explanation of what is going well and what needs attention",
  "health_score": {
    "score": 1,
    "label": "Needs Work | Getting There | Healthy | Thriving",
    "explanation": "one-sentence reason"
  },
  "key_findings": ["finding 1", "finding 2", "finding 3"],
  "segment_insights": [
    {
      "segment_name": "plain customer group name",
      "insight": "one sentence about what this group means",
      "action": "one practical action to take this week",
      "priority": "urgent | important | monitor | low"
    }
  ],
  "top_3_actions": [
    {
      "action": "specific action",
      "why": "why it matters",
      "how": "how a Ghana SME can do it",
      "segment_target": "which group this targets"
    }
  ],
  "revenue_insight": "one sentence",
  "risk_alert": "one sentence or null",
  "encouragement": "one warm closing sentence"
}
""".strip()


class GroqRateLimiter:
    """Application-side rate limiting based on completed LLM calls in the jobs table."""

    def check_user_cooldown(self, user_id: int, db: Session) -> Tuple[bool, str]:
        latest_llm_job = (
            db.query(Job)
            .filter(Job.user_id == user_id)
            .filter(Job.llm_generated_at.isnot(None))
            .order_by(Job.llm_generated_at.desc())
            .first()
        )
        if not latest_llm_job or not latest_llm_job.llm_generated_at:
            return True, "ok"

        elapsed = (datetime.utcnow() - latest_llm_job.llm_generated_at).total_seconds()
        if elapsed < GROQ_USER_COOLDOWN_SECONDS:
            remaining = max(1, int(GROQ_USER_COOLDOWN_SECONDS - elapsed))
            return False, f"Please wait {remaining} seconds before requesting another AI summary."

        return True, "ok"

    def check_user_daily_limit(self, user_id: int, db: Session) -> Tuple[bool, str]:
        day_start = datetime.utcnow() - timedelta(days=1)
        count = (
            db.query(func.count(Job.id))
            .filter(Job.user_id == user_id)
            .filter(Job.llm_generated_at >= day_start)
            .scalar()
            or 0
        )
        if count >= GROQ_USER_DAILY_LIMIT:
            return False, "Daily AI summary limit reached for this user."
        return True, "ok"

    def check_global_rpm(self, user_id: int, db: Session) -> Tuple[bool, str]:
        _ = user_id
        minute_start = datetime.utcnow() - timedelta(minutes=1)
        count = (
            db.query(func.count(Job.id))
            .filter(Job.llm_generated_at >= minute_start)
            .scalar()
            or 0
        )
        if count >= GROQ_REQUESTS_PER_MINUTE:
            return False, "AI summary rate limit reached. Please try again shortly."
        return True, "ok"

    def check_global_daily(self, user_id: int, db: Session) -> Tuple[bool, str]:
        _ = user_id
        day_start = datetime.utcnow() - timedelta(days=1)
        count = (
            db.query(func.count(Job.id))
            .filter(Job.llm_generated_at >= day_start)
            .scalar()
            or 0
        )
        if count >= GROQ_DAILY_REQUEST_LIMIT:
            return False, "Daily AI summary quota reached. Please try again tomorrow."
        return True, "ok"

    def check_all(self, user_id: int, db: Session) -> Tuple[bool, str]:
        for check in (
            self.check_user_cooldown,
            self.check_user_daily_limit,
            self.check_global_rpm,
            self.check_global_daily,
        ):
            allowed, reason = check(user_id, db)
            if not allowed:
                return False, reason
        return True, "ok"


def _safe_number(value: Any, decimals: int = 2) -> float:
    try:
        return round(float(value or 0), decimals)
    except (TypeError, ValueError):
        return 0.0


def _segment_alias(segment_name: str) -> str:
    return str(segment_name or "Customer Group").strip() or "Customer Group"


def prepare_groq_context(results: Dict[str, Any]) -> Dict[str, Any]:
    """Extract a compact PII-free summary from pipeline results."""
    meta = results.get("meta", {}) or {}
    segments = results.get("segments", []) or []
    shap = results.get("shap", {}) or {}
    preprocessing = results.get("preprocessing", {}) or {}
    eda = preprocessing.get("eda", {}) or {}
    outlier = results.get("outlier_treatment", {}) or {}

    return {
        "business_overview": {
            "business_type": preprocessing.get("business_type", "General Retail"),
            "total_customers": int(meta.get("num_customers") or 0),
            "total_transactions": int(meta.get("num_transactions") or 0),
            "total_revenue_ghs": _safe_number(meta.get("total_revenue"), 2),
            "num_segments": int(meta.get("num_clusters") or len(segments) or 0),
            "analysis_quality_score": _safe_number(meta.get("silhouette_score"), 3),
        },
        "segments": [
            {
                "name": _segment_alias(str(segment.get("segment_label") or "Customer Group")),
                "short_name": str(segment.get("segment_short_name") or ""),
                "description": str(segment.get("description") or ""),
                "current_actions": [
                    str(action)
                    for action in (segment.get("recommended_actions") or [])
                    if action
                ][:3],
                "customer_count": int(segment.get("num_customers") or 0),
                "percentage": _safe_number(segment.get("percentage"), 1),
                "avg_recency_days": _safe_number(segment.get("avg_recency"), 1),
                "avg_frequency": _safe_number(segment.get("avg_frequency"), 1),
                "avg_spend_ghs": _safe_number(segment.get("avg_monetary"), 2),
                "total_revenue_ghs": _safe_number(segment.get("total_revenue"), 2),
            }
            for segment in segments
        ],
        "feature_importance": [
            {
                "feature": str(item.get("feature") or ""),
                "importance": _safe_number(item.get("importance"), 4),
            }
            for item in ((shap.get("ranked_features") or []) if shap.get("available") else [])
        ],
        "data_quality": {
            "rows_processed": int(eda.get("rows") or 0),
            "duplicates_removed": int(eda.get("duplicates") or 0),
            "missing_values": int(eda.get("missing_values") or 0),
            "outliers_capped": int((outlier.get("capped_above") or 0) + (outlier.get("capped_below") or 0)),
        },
        "top_categories": (eda.get("top_categories") or [])[:5],
        "top_products": (eda.get("top_products") or [])[:5],
    }


def _build_user_prompt(context: Dict[str, Any]) -> str:
    business = context["business_overview"]
    quality_score = business.get("analysis_quality_score", 0)

    segment_lines = []
    exact_names = []
    for segment in context.get("segments", []):
        exact_names.append(segment["name"])
        existing_actions = "; ".join(segment.get("current_actions", [])[:2]) or "No default actions listed."
        segment_lines.append(
            (
                "- Exact segment name: {name}\n"
                "  Simple label currently shown in UI/PDF: {short_name}\n"
                "  Segment size: {customer_count} customers ({percentage}%)\n"
                "  Last bought {avg_recency_days} days ago on average, "
                "buy {avg_frequency} times on average, "
                "spend GHS {avg_spend_ghs} on average, "
                "total revenue GHS {total_revenue_ghs}.\n"
                "  Current meaning: {description}\n"
                "  Current actions: {existing_actions}"
            ).format(existing_actions=existing_actions, **segment)
        )

    feature_lines = []
    feature_label_map = {
        "recency": "How recently customers bought",
        "frequency": "How often customers come back",
        "monetary": "How much customers spend",
    }
    for item in context.get("feature_importance", []):
        feature_name = feature_label_map.get(item["feature"].lower(), item["feature"].replace("_", " ").title())
        feature_lines.append(f"- {feature_name}: {item['importance']}")

    quality = context.get("data_quality", {})
    prompt = f"""
Here is the customer analysis summary for a {business.get('business_type', 'General Retail')} business in Ghana.

Business overview:
- Total customers: {business.get('total_customers', 0)}
- Total transactions: {business.get('total_transactions', 0)}
- Total revenue: GHS {business.get('total_revenue_ghs', 0)}
- Number of customer groups found: {business.get('num_segments', 0)}
- Grouping confidence score: {quality_score} out of 1.0

Customer groups:
{chr(10).join(segment_lines) if segment_lines else '- No customer groups available.'}

What mostly separates one group from another:
{chr(10).join(feature_lines) if feature_lines else '- Feature importance data not available.'}

Data quality:
- Rows processed: {quality.get('rows_processed', 0)}
- Duplicate rows removed: {quality.get('duplicates_removed', 0)}
- Missing values handled: {quality.get('missing_values', 0)}
- Extreme values smoothed: {quality.get('outliers_capped', 0)}

Important naming rule:
- Return segment_insights using these exact segment_name values only:
  {", ".join(exact_names) if exact_names else "No customer groups available"}
- Keep each group name unique. If a name includes " - Group 1" or " - Group 2", preserve that exact suffix.
- Improve the recommendation and explanation for each group, but do not invent new group names.
""".strip()

    if len(prompt) > GROQ_MAX_INPUT_CHARS:
        return prompt[:GROQ_MAX_INPUT_CHARS]
    return prompt


def _fallback_business_narrative(results: Dict[str, Any]) -> Dict[str, Any]:
    """Return a deterministic plain-language fallback when Groq is unavailable."""
    context = prepare_groq_context(results)
    business = context["business_overview"]
    segments = context["segments"]
    top_segment = max(segments, key=lambda item: item["total_revenue_ghs"], default=None)
    risk_segment = next(
        (
            segment
            for segment in segments
            if any(
                token in segment["name"].lower()
                for token in ("risk", "follow-up", "cooling-off", "likely left", "quiet low-activity")
            )
        ),
        None,
    )
    confidence = business.get("analysis_quality_score", 0)
    if confidence >= 0.55:
        health_label = "Healthy"
        score = 8
    elif confidence >= 0.35:
        health_label = "Getting There"
        score = 6
    else:
        health_label = "Needs Work"
        score = 4

    top_actions = []
    for segment in segments[:3]:
        segment_name = segment["name"]
        existing_action = (segment.get("current_actions") or [""])[0]
        if "best repeat buyers" in segment_name.lower():
            action = "Send a thank-you offer to your best repeat buyers"
            how = "Use a WhatsApp broadcast or personal calls with a loyalty discount."
        elif any(token in segment_name.lower() for token in ("risk", "follow-up", "cooling-off", "likely left", "quiet low-activity")):
            action = f"Follow up with {segment_name}"
            how = "Send a simple 'we miss you' WhatsApp message and a comeback offer this week."
        else:
            action = existing_action or f"Keep {segment_name} active"
            how = "Share new stock, bundles, or a small loyalty offer to encourage another purchase."
        top_actions.append({
            "action": action,
            "why": f"This group contains {segment['customer_count']} customers and contributed GHS {segment['total_revenue_ghs']}.",
            "how": how,
            "segment_target": segment_name,
        })

    return {
        "headline": (results.get("story_summary") or {}).get(
            "headline",
            f"{top_segment['name']} is your strongest customer group right now." if top_segment else "Your customer analysis is ready."
        ),
        "story": (results.get("story_summary") or {}).get(
            "narrative",
            f"You have {business['total_customers']} customers and {business['total_transactions']} transactions in this analysis."
        ),
        "health_score": {
            "score": score,
            "label": health_label,
            "explanation": "This is a simple summary of how clearly your customer groups separate and how many customers sit in stronger repeat-buyer groups."
        },
        "key_findings": [
            f"{top_segment['name']} generated GHS {top_segment['total_revenue_ghs']} from {top_segment['customer_count']} customers." if top_segment else "No top customer group was available.",
            f"{risk_segment['customer_count']} customers are in {risk_segment['name']} and should be followed up." if risk_segment else "No urgent risk group was detected from the current segment labels.",
            f"The platform found {business['num_segments']} customer groups from {business['total_customers']} customers.",
        ],
        "segment_insights": [
            {
                "segment_name": segment["name"],
                "insight": segment["description"] or f"{segment['name']} represents {segment['customer_count']} customers.",
                "action": (segment.get("current_actions") or [f"Review this group and plan a follow-up campaign for {segment['name']}."])[0],
                "priority": "urgent" if any(
                    token in segment["name"].lower()
                    for token in ("risk", "follow-up", "cooling-off", "likely left")
                ) else "important",
            }
            for segment in segments
        ],
        "top_3_actions": top_actions,
        "revenue_insight": (
            f"{top_segment['name']} currently brings the highest revenue at GHS {top_segment['total_revenue_ghs']}."
            if top_segment else "Revenue is spread across the customer groups in this analysis."
        ),
        "risk_alert": (
            f"{risk_segment['name']} needs follow-up because this group contains {risk_segment['customer_count']} customers."
            if risk_segment else None
        ),
        "encouragement": "Use these customer groups to send focused follow-up messages and protect your repeat buyers.",
        "source": "fallback",
    }


def generate_llm_analysis(results: Dict[str, Any]) -> Optional[Dict[str, Any]]:
    """
    Generate Groq narrative from PII-free aggregate results.

    Returns a parsed JSON dictionary when Groq succeeds, otherwise a deterministic
    fallback narrative so the UI still gets business-friendly text.
    """
    if not GROQ_ENABLED:
        return _fallback_business_narrative(results)

    try:
        from groq import Groq
    except Exception as exc:
        logger.warning("Groq SDK unavailable; using fallback narrative: %s", exc)
        return _fallback_business_narrative(results)

    client = Groq(api_key=GROQ_API_KEY)
    prompt = _build_user_prompt(prepare_groq_context(results))

    for attempt in range(max(1, GROQ_RETRY_ATTEMPTS)):
        try:
            response = client.chat.completions.create(
                model=GROQ_MODEL,
                messages=[
                    {"role": "system", "content": SYSTEM_PROMPT},
                    {"role": "user", "content": prompt},
                ],
                temperature=0.3,
                max_tokens=GROQ_MAX_OUTPUT_TOKENS,
                response_format={"type": "json_object"},
            )
            raw_content = response.choices[0].message.content or "{}"
            parsed = json.loads(raw_content)
            parsed["source"] = "groq"
            parsed["model"] = GROQ_MODEL
            return parsed
        except Exception as exc:
            status_code = getattr(getattr(exc, "response", None), "status_code", None)
            if status_code == 429 and attempt < max(1, GROQ_RETRY_ATTEMPTS) - 1:
                retry_after = getattr(getattr(exc, "response", None), "headers", {}).get("retry-after")
                wait_seconds = float(retry_after) if retry_after else float(2 ** attempt)
                time.sleep(max(1.0, min(wait_seconds, 20.0)))
                continue
            logger.warning("Groq request failed; using fallback narrative: %s", exc)
            return _fallback_business_narrative(results)

    return _fallback_business_narrative(results)
