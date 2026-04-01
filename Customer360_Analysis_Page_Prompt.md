# Customer360 — Analysis Page Feature Build Prompt

## Overview

Build a full end-to-end customer segmentation analysis feature for the Customer360 web application. The feature accepts a CSV upload from an authenticated user, maps columns interactively, runs the RFM pipeline via the Python backend, and renders a rich insight dashboard on the frontend. It also includes Groq LLM-powered advertiser-style customer profiling (inferring lifestyle, buying personality, and churn risk from purchase behaviour — the way data brokers and advertisers do), an in-app rate limiter with cooldown, and a one-click PDF download of the full segmentation report.

---

## Project directory structure

The project already has a `backend/` and `frontend/` directory. Add a third top-level directory:

```
customer360/
├── backend/          # FastAPI Python app (existing)
├── frontend/         # PHP + HTML/CSS/JS (existing)
└── models/           # NEW — stores ML artifacts
    ├── scaler.pkl              # Fitted StandardScaler from Colab pipeline
    ├── segment_map.json        # Cluster ID → segment name mapping
    └── cluster_rfm_profile.csv # Per-cluster median RFM scores
```

The `models/` directory is populated by running the fixed Colab notebook (`Customer360_Fixed_Pipeline.ipynb`) and downloading the `customer360_model_bundle.zip`. Extract that zip directly into `models/`.

---

## Feature scope — what to build

### 1. Analysis page route

Create a new page at `/analysis` (or `/dashboard/analysis`) in the PHP frontend. This page has three sequential states that the user moves through:

- **State 1 — Upload:** user uploads a CSV file
- **State 2 — Column mapping:** user maps their CSV columns to the required RFM fields
- **State 3 — Dashboard:** the results are displayed as charts, tables, and AI insights

---

### 2. File upload interface (State 1)

Build a drag-and-drop upload zone that accepts `.csv` files only. Requirements:

- Drag-and-drop area with a dashed border and an upload icon
- Click-to-browse as fallback
- Show the filename and row count preview immediately after selection (parse the first line client-side in JavaScript to count columns)
- File size limit: 50MB enforced client-side before the request is sent
- Show a clear error if the file is not a `.csv`
- On valid file selection, automatically advance to State 2

---

### 3. Column mapping interface (State 2)

After upload, parse the CSV headers client-side and display an interactive mapping table. The table has two columns: the left column shows the user's actual CSV column names, and the right column shows a dropdown for each row letting the user assign that column to one of the required pipeline fields.

The required pipeline fields to map to are:

| Field label | Required? | Description |
|---|---|---|
| Customer ID | Required | Unique identifier per customer |
| Purchase Date | Required | Transaction date |
| Revenue / Total Amount | Required | Transaction value |
| Order ID | Optional | Unique order identifier |
| Product Name | Optional | Product purchased |
| Category | Optional | Product category |
| Unit Price | Optional | Price per unit |
| Quantity | Optional | Units per transaction |
| Discount Amount | Optional | Discount applied |
| Promo Code | Optional | Promotional code used |
| Payment Method | Optional | Payment type |
| Shipping Method | Optional | Delivery method |

UI behaviour:

- Auto-suggest the mapping using fuzzy keyword matching in JavaScript on page load (e.g. if the user's column is called `cust_id`, auto-select "Customer ID"). The keywords to match against are the same ones used in `detect_schema()` in the pipeline.
- Highlight unmapped required fields in red
- Disable the "Run Analysis" button until all three required fields are mapped
- Show a "Skip optional fields" link that marks all optional fields as unmapped/skipped at once
- When the user clicks "Run Analysis", POST the CSV file and the column mapping JSON to the FastAPI backend

---

### 4. Loading state with animated progress (between State 2 and State 3)

While the backend is running, show a full-page loading overlay. It must have:

- An animated circular spinner (CSS only, no external library)
- A progress bar that animates from 0% to 95% over approximately 45 seconds using a CSS ease-out curve (it stops at 95% and only completes to 100% when the server responds)
- A rotating set of status messages that update every 4–6 seconds, cycling through:

```
"Reading your transaction data..."
"Cleaning and validating records..."
"Computing RFM scores for each customer..."
"Scaling features for clustering..."
"Finding optimal number of segments..."
"Running K-Means, GMM, and Hierarchical clustering..."
"Selecting best algorithm by Silhouette Score..."
"Labelling segments: Champions, At Risk, Loyalists..."
"Running SHAP explainability analysis..."
"Generating your insights..."
"Almost done — building your dashboard..."
```

- The overlay must be dismissible if the request takes longer than 3 minutes (show a "This is taking longer than expected — still working..." message after 2 minutes)

---

### 5. FastAPI backend endpoint

Add a new endpoint to the FastAPI backend: `POST /api/analyze`.

The endpoint receives a multipart form POST with two fields:
- `file` — the CSV file
- `mapping` — a JSON string of `{ "pipeline_field": "csv_column_name", ... }`

The endpoint must:

1. Load `models/scaler.pkl` and `models/segment_map.json` at startup using `@app.on_event("startup")`, not on each request
2. Parse the CSV and remap column names according to the `mapping` JSON so that the pipeline's `detect_schema()` receives columns with recognisable names
3. Run the full pipeline in order: `clean_data` → `compute_rfm` → `scale_rfm` (using the loaded scaler for `transform`, not `fit_transform`) → `find_optimal_k` → `run_all_clustering` → `label_segments` → `explain_with_shap` → `validate_clusters`
4. Execute the `generate_pdf_report` function from the Colab pipeline and save the output PDF to a temp directory
5. Return a JSON response (see structure below) plus store the PDF path in the user's session so it can be downloaded later via `GET /api/report/download`

Response JSON structure:

```json
{
  "status": "success",
  "meta": {
    "n_transactions": 1521,
    "n_customers": 347,
    "n_segments": 4,
    "best_algorithm": "K-Means",
    "silhouette_score": 0.612,
    "davies_bouldin": 0.441,
    "stability": "Excellent",
    "avg_ari": 0.943,
    "currency": "GHS",
    "business_type": "Fashion Retail"
  },
  "segments": [
    {
      "name": "Champions",
      "customer_count": 42,
      "customer_pct": 12.1,
      "avg_recency": 18.3,
      "avg_frequency": 7.2,
      "avg_monetary": 1240.50,
      "total_revenue": 52101.00,
      "revenue_share": 34.2,
      "discount_rate": 5.1,
      "promo_rate": 3.2,
      "top_category": "Dresses",
      "top_product": "Kente Dress",
      "top_payment": "Mobile Money"
    }
  ],
  "shap": {
    "feature_importances": {
      "Recency": 0.38,
      "Frequency": 0.35,
      "Monetary": 0.27
    },
    "surrogate_accuracy": 0.94
  },
  "validation": {
    "anova": {
      "Recency":   { "F": 142.3, "p": 1.2e-45, "significant": true },
      "Frequency": { "F": 98.1,  "p": 3.4e-38, "significant": true },
      "Monetary":  { "F": 201.4, "p": 2.1e-52, "significant": true }
    },
    "ari_stability": { "avg": 0.943, "std": 0.021, "rating": "Excellent" }
  },
  "charts": {
    "rfm_distributions":    "/api/charts/rfm_distributions.png",
    "segment_distribution": "/api/charts/segment_distribution.png",
    "revenue_pareto":       "/api/charts/revenue_pareto.png",
    "radar_chart":          "/api/charts/radar_chart.png",
    "shap_bar":             "/api/charts/shap_bar.png",
    "pca_clusters_2d":      "/api/charts/pca_clusters_2d.png",
    "algorithm_comparison": "/api/charts/algorithm_comparison.png",
    "rfm_violin_plots":     "/api/charts/rfm_violin_plots.png"
  },
  "pdf_ready": true
}
```

Also add `GET /api/charts/{filename}` to serve the generated chart PNG files.

---

### 6. Results dashboard (State 3)

Render the dashboard entirely from the JSON response using JavaScript. Do not reload the page. The dashboard has the following sections rendered in order:

#### 6a. Summary KPI bar

Four metric cards in a horizontal row:

- Total customers analysed
- Segments found
- Best algorithm used (with Silhouette score shown below in smaller text)
- Total revenue analysed (in detected currency)

#### 6b. Segment cards grid

One card per segment, displayed in a 2-column grid on desktop, 1-column on mobile. Each card shows:

- Segment name as a coloured heading (use a consistent colour per segment — Champions = green, At Risk = red, Loyal = blue, Hibernating = gray, etc.)
- Customer count and percentage
- Three RFM badges: avg Recency (days), avg Frequency (orders), avg Monetary (currency value)
- Revenue share as a horizontal fill bar
- Top category and top payment method
- A "Get AI Profile" button (see section 7)

#### 6c. Charts section

Embed the chart images returned in `charts` from the API response. Display them in a 2-column masonry-style grid. Each chart has a title above it and a one-sentence plain-English caption below it explaining what the chart shows. The captions are hardcoded based on chart type — they do not need to come from the API.

Charts to display and their captions:

| Chart | Caption |
|---|---|
| `segment_distribution` | "How your customers are split across segments by count and percentage." |
| `revenue_pareto` | "Which segments drive the most revenue — the 80/20 rule in action." |
| `radar_chart` | "RFM profile comparison across all segments — outward = better performance." |
| `rfm_violin_plots` | "Distribution of Recency, Frequency, and Monetary value within each segment." |
| `shap_bar` | "Which RFM feature most strongly determines which segment a customer falls into." |
| `pca_clusters_2d` | "Your customer clusters visualised in 2D space using PCA dimensionality reduction." |
| `algorithm_comparison` | "How K-Means, GMM, and Hierarchical Clustering compared — the winning algorithm is highlighted." |

#### 6d. Validation summary panel

A collapsible panel labelled "Statistical validation" that shows:

- Three ANOVA rows (one per RFM feature): F-statistic, p-value, and a green "Significant" or red "Not significant" badge
- ARI stability score with a plain-English rating label
- A one-paragraph plain-English explanation of what these numbers mean for a non-technical business owner

#### 6e. SHAP feature importance bar

A horizontal bar chart rendered in CSS (not a canvas or chart library) showing the three RFM features and their normalised SHAP importance scores. Each bar has the feature name on the left, a coloured fill proportional to importance, and the percentage on the right.

---

### 7. Groq LLM integration — advertiser-style customer profiling

Add Groq API integration for on-demand segment profiling. Use the free Groq API (`api.groq.com/openai/v1/chat/completions`) with model `llama3-8b-8192`.

The goal is not just to describe the segment — it is to **infer** things about the customers in that segment the way an advertiser or data broker would: lifestyle, buying personality, churn risk, and what ad messaging would actually convert them. This is inspired by browser fingerprinting tools like [yourinfo](https://github.com/siinghd/yourinfo) which infer "Developer: Yes (80%)" or "Designer: Yes (75%)" purely from behavioural signals — the same logic applied to purchase data.

#### 7a. How it triggers

Each segment card has a "Get AI Profile" button. When clicked, it opens an inline panel directly below the card (not a modal) and sends a request to the PHP proxy endpoint `POST /api/groq-insight` with the enriched segment payload. The panel shows a loading spinner while waiting, then renders the structured JSON response as a formatted profile card.

#### 7b. PHP proxy endpoint

Create `frontend/api/groq-insight.php`. This file:

- Receives the enriched segment JSON from the frontend (see payload structure in 7c)
- Builds the inference prompt (see 7d)
- Calls the Groq API with your key stored in a `.env` file (never hardcoded)
- Parses and returns the JSON response to the frontend
- Implements server-side rate limiting (see 7g)

#### 7c. Frontend payload — what to send to the proxy

In `analysis.js`, when the user clicks "Get AI Profile", compute and POST this enriched object. The derived ratio fields are calculated client-side in JavaScript before the request is sent:

```javascript
const overallAvgMonetary = meta.total_revenue / meta.n_customers;

const payload = {
  // Context
  segment_name:       seg.name,
  business_type:      meta.business_type,
  currency:           meta.currency,
  n_total_customers:  meta.n_customers,
  total_revenue:      meta.total_revenue,
  n_segments:         meta.n_segments,

  // Core RFM
  customer_count:     seg.customer_count,
  customer_pct:       seg.customer_pct,
  avg_recency:        seg.avg_recency,
  avg_frequency:      seg.avg_frequency,
  avg_monetary:       seg.avg_monetary,
  revenue_share:      seg.revenue_share,

  // Behavioural signals
  discount_rate:      seg.discount_rate,
  promo_rate:         seg.promo_rate,
  top_category:       seg.top_category,
  top_product:        seg.top_product,
  top_payment:        seg.top_payment,

  // Derived inference signals (computed client-side)
  monetary_vs_avg:    (seg.avg_monetary / overallAvgMonetary).toFixed(2),
  is_high_value:      seg.revenue_share > (100 / meta.n_segments) * 1.5,
  is_price_sensitive: seg.discount_rate > 25 || seg.promo_rate > 25,
  is_frequent_buyer:  seg.avg_frequency > 4,
  is_lapsed:          seg.avg_recency > 90,
  payment_is_digital: ['mobile money', 'card', 'bank card', 'momo'].some(
    p => (seg.top_payment || '').toLowerCase().includes(p)
  ),
};
```

#### 7d. The Groq inference prompt

This is the core of the feature. The prompt instructs Groq to reason like an advertiser or data broker — inferring lifestyle and intent from purchase signals, not just describing what the data shows. It must return **raw JSON only** so the frontend can parse and render it as a structured profile card.

Use this exact prompt template in `groq-insight.php`:

```php
$prompt = <<<PROMPT
You are a customer intelligence analyst working for a {$business_type} business in Ghana.
You analyse purchase data the same way advertisers and data brokers build consumer profiles —
inferring lifestyle, habits, and intent from behavioural signals, not just describing what happened.

SEGMENT: "{$segment_name}"
DATA:
- {$customer_count} customers ({$customer_pct}% of total base)
- Average days since last purchase: {$avg_recency}
- Average number of orders: {$avg_frequency}
- Average lifetime spend per customer: {$currency} {$avg_monetary}
- Share of total revenue: {$revenue_share}%
- Uses discounts: {$discount_rate}% of the time
- Uses promo codes: {$promo_rate}% of the time
- Favourite product category: {$top_category}
- Favourite product: {$top_product}
- Preferred payment method: {$top_payment}
- Spends {$monetary_vs_avg}x the average customer

INFERENCE TASK:
From this purchase data alone — the way an advertiser or data broker would — infer and return ONLY
this JSON object. No explanation. No preamble. No markdown. Raw JSON only.

{
  "who_they_are": {
    "likely_occupation": "<infer from spend level, payment method, product choices>",
    "income_bracket": "<low | medium | medium-high | high>",
    "age_range": "<e.g. 20-30 | 25-35 | 30-45>",
    "lifestyle_tag": "<one short tag e.g. 'Busy professional' or 'Budget-conscious student'>",
    "confidence": "<low | medium | high>"
  },
  "buying_personality": {
    "type": "<one of: Impulse Buyer | Deliberate Shopper | Deal Hunter | Brand Loyal | Occasional Splurger | Habitual Regular>",
    "price_sensitivity": "<low | medium | high>",
    "brand_loyalty": "<low | medium | high>",
    "digital_savviness": "<low | medium | high>"
  },
  "advertiser_signals": [
    "<signal 1: what ad type or messaging style would convert this person, e.g. 'Responds to scarcity messaging'>",
    "<signal 2: e.g. 'Best reached via mobile money-linked promotions'>",
    "<signal 3: e.g. 'Price anchoring works — show original price with discount'>"
  ],
  "churn_risk": {
    "level": "<low | medium | high>",
    "reason": "<one sentence max>"
  },
  "growth_opportunity": {
    "action": "<single most valuable action for the business owner this week>",
    "expected_impact": "<what improvement to expect, in plain language, one sentence>"
  },
  "segment_headline": "<a punchy 6-8 word label that captures this segment's essence, like an ad agency would write it>"
}
PROMPT;
```

This prompt is structured to use under 500 input tokens with a predictable JSON output under 300 tokens, keeping total usage well within Groq's free tier limits per call.

#### 7e. Frontend rendering — the profile card

Since Groq returns structured JSON, parse it and render it as a profile card. In `analysis.js`, replace the text-rendering logic with this function:

```javascript
function renderGroqInsight(container, rawResponse) {
  // Groq sometimes wraps JSON in backtick fences — strip them before parsing
  let parsed;
  try {
    const clean = rawResponse.replace(/```json|```/g, '').trim();
    parsed = JSON.parse(clean);
  } catch (e) {
    container.innerHTML = `<p class="groq-error">${rawResponse}</p>`;
    return;
  }

  const conf = parsed.who_they_are.confidence;
  const confColor = conf === 'high'   ? 'var(--color-text-success)'
                  : conf === 'medium' ? 'var(--color-text-warning)'
                  :                    'var(--color-text-secondary)';

  container.innerHTML = `
    <div class="groq-insight-panel">

      <div class="insight-headline">${parsed.segment_headline}</div>

      <div class="insight-grid">

        <div class="insight-block">
          <div class="insight-block-title">Who they are</div>
          <div class="insight-row">
            <span>Likely occupation</span>
            <span>${parsed.who_they_are.likely_occupation}</span>
          </div>
          <div class="insight-row">
            <span>Income bracket</span>
            <span>${parsed.who_they_are.income_bracket}</span>
          </div>
          <div class="insight-row">
            <span>Age range</span>
            <span>${parsed.who_they_are.age_range}</span>
          </div>
          <div class="insight-row">
            <span>Lifestyle</span>
            <span>${parsed.who_they_are.lifestyle_tag}</span>
          </div>
          <div class="insight-row">
            <span>Inference confidence</span>
            <span style="color:${confColor}">${conf}</span>
          </div>
        </div>

        <div class="insight-block">
          <div class="insight-block-title">Buying personality</div>
          <div class="insight-row">
            <span>Type</span>
            <span>${parsed.buying_personality.type}</span>
          </div>
          <div class="insight-row">
            <span>Price sensitivity</span>
            <span>${parsed.buying_personality.price_sensitivity}</span>
          </div>
          <div class="insight-row">
            <span>Brand loyalty</span>
            <span>${parsed.buying_personality.brand_loyalty}</span>
          </div>
          <div class="insight-row">
            <span>Digital savviness</span>
            <span>${parsed.buying_personality.digital_savviness}</span>
          </div>
        </div>

      </div>

      <div class="insight-block full-width">
        <div class="insight-block-title">Advertiser signals</div>
        ${parsed.advertiser_signals.map(s => `
          <div class="insight-signal">→ ${s}</div>
        `).join('')}
      </div>

      <div class="insight-grid">

        <div class="insight-block">
          <div class="insight-block-title">Churn risk</div>
          <div class="churn-badge churn-${parsed.churn_risk.level}">
            ${parsed.churn_risk.level.toUpperCase()}
          </div>
          <div class="insight-subtext">${parsed.churn_risk.reason}</div>
        </div>

        <div class="insight-block">
          <div class="insight-block-title">Growth opportunity</div>
          <div class="insight-action">${parsed.growth_opportunity.action}</div>
          <div class="insight-subtext">${parsed.growth_opportunity.expected_impact}</div>
        </div>

      </div>

    </div>
  `;
}
```

#### 7f. CSS for the profile card

Add the following to `analysis.css`:

```css
.groq-insight-panel {
  border: 1px solid var(--color-border-secondary);
  border-radius: var(--border-radius-lg);
  padding: 1.25rem;
  margin-top: 0.75rem;
  background: var(--color-background-secondary);
}
.insight-headline {
  font-size: 1.05rem;
  font-weight: 500;
  color: var(--color-text-primary);
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--color-border-tertiary);
}
.insight-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}
@media (max-width: 600px) {
  .insight-grid { grid-template-columns: 1fr; }
}
.insight-block {
  background: var(--color-background-primary);
  border: 1px solid var(--color-border-tertiary);
  border-radius: var(--border-radius-md);
  padding: 0.75rem;
}
.insight-block.full-width {
  grid-column: 1 / -1;
  margin-bottom: 1rem;
}
.insight-block-title {
  font-size: 0.7rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-tertiary);
  margin-bottom: 0.5rem;
}
.insight-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  padding: 0.3rem 0;
  border-bottom: 1px solid var(--color-border-tertiary);
  color: var(--color-text-secondary);
}
.insight-row:last-child { border-bottom: none; }
.insight-row span:last-child {
  color: var(--color-text-primary);
  font-weight: 500;
  text-align: right;
  max-width: 55%;
}
.insight-signal {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  padding: 0.3rem 0;
  border-bottom: 1px solid var(--color-border-tertiary);
}
.insight-signal:last-child { border-bottom: none; }
.churn-badge {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 500;
  padding: 0.2rem 0.65rem;
  border-radius: 999px;
  margin-bottom: 0.5rem;
  letter-spacing: 0.05em;
}
.churn-low    { background: var(--color-background-success); color: var(--color-text-success); }
.churn-medium { background: var(--color-background-warning); color: var(--color-text-warning); }
.churn-high   { background: var(--color-background-danger);  color: var(--color-text-danger);  }
.insight-action {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-text-primary);
  margin-bottom: 0.25rem;
}
.insight-subtext {
  font-size: 0.8rem;
  color: var(--color-text-secondary);
  line-height: 1.5;
}
.groq-error {
  font-size: 0.875rem;
  color: var(--color-text-danger);
  padding: 0.5rem;
}
```

#### 7g. In-app rate limiting and cooldown

Implement a two-layer rate limiter: client-side for UX, server-side as the actual guard.

**Client-side (localStorage):**

- Maximum 5 Groq requests per user per 10-minute window
- Track requests with a timestamp array stored in `localStorage` under the key `groq_requests`
- Before each request, filter the array to remove timestamps older than 10 minutes, then check the count
- If the count is at 5, show an inline banner above the segment cards: "You've used 5 AI profiles in the last 10 minutes. Next profile available in X minutes." where X counts down in real time using `setInterval`
- When a cooldown is active, disable all "Get AI Profile" buttons and apply an opacity of 0.4
- After the cooldown expires, re-enable all buttons automatically without a page reload

**Server-side (PHP session):**

In `groq-insight.php`, track the last 3 request timestamps per session in `$_SESSION['groq_timestamps']`. On each request, filter to the last 60 seconds. If 3 or more remain, return HTTP 429:

```php
header('HTTP/1.1 429 Too Many Requests');
echo json_encode(['error' => 'Too many requests. Please wait a moment before loading another profile.']);
exit;
```

The frontend must handle a 429 response by displaying the error message inline inside the profile panel rather than crashing.

---

### 8. PDF download

#### 8a. Download button

Add a "Download full report PDF" button in the dashboard header, always visible once the dashboard is loaded. It should be styled as a primary action button — not a small link.

#### 8b. PHP download handler

Create `frontend/api/download-report.php`. It calls `GET /api/report/download` on the FastAPI backend, streams the PDF response back to the browser with the correct headers:

```php
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Customer360_Report.pdf"');
```

#### 8c. FastAPI download endpoint

Add `GET /api/report/download` to the FastAPI backend. It reads the PDF file path stored in the user's session from the last analysis run and returns it as a `FileResponse`. If no analysis has been run in this session, return HTTP 404.

The PDF is generated by `generate_pdf_report()` from the Colab notebook — that function already produces the full report including cover page, per-segment profiles, SHAP explanations, validation results, and business recommendations. Do not recreate this logic — call it directly.

---

## Technical constraints and notes

**Column remapping before pipeline:** The mapping JSON from the frontend must be applied to the dataframe before calling `detect_schema()`. Rename the dataframe columns using the mapping so that the pipeline's keyword matching finds them correctly. Example: if the user mapped `cust_id` to Customer ID, rename that column to `CustomerID` before passing the dataframe to `detect_schema()`.

**Scaler usage:** In the analysis endpoint, use `scaler.transform()` not `scaler.fit_transform()`. The scaler was fitted on the training data in Colab. Using `fit_transform` on each new upload would give inconsistent scaling across analyses.

**Chart file serving:** Charts are generated as PNG files to a temp directory per analysis run. The FastAPI endpoint `GET /api/charts/{filename}` serves them. Include a cleanup job that deletes chart files older than 2 hours to prevent disk bloat.

**CORS:** The FastAPI backend must allow requests from the PHP frontend's domain. Add `CORSMiddleware` with the correct origin.

**Error handling:** Every state transition (upload → mapping, mapping → dashboard) must handle errors gracefully. If the pipeline fails mid-run, return a structured error JSON with a `reason` field and display it in the loading overlay before it closes.

**Groq JSON parsing:** Groq's `llama3-8b-8192` model sometimes wraps JSON in markdown code fences. Always strip these before calling `JSON.parse()` on the frontend and before returning to the caller in PHP.

**Mobile responsiveness:** The dashboard must be fully usable on mobile. The segment cards grid collapses to 1 column. Charts stack vertically. The KPI bar wraps to 2×2. The loading overlay is full-screen on all devices. The Groq profile card's two-column grid collapses to single column below 600px.

**Session handling:** Use PHP sessions to pass the PDF path and analysis metadata between the PHP frontend and the FastAPI backend. Store the FastAPI-returned session token in `$_SESSION['analysis_token']` and pass it as a Bearer token on the download request.

---

## File checklist — new files to create

```
backend/app/routes/analyze.py        # POST /api/analyze endpoint
backend/app/routes/charts.py         # GET /api/charts/{filename} endpoint
backend/app/routes/report.py         # GET /api/report/download endpoint
backend/app/analytics/core.py        # Shared core functions from Colab Section A
models/scaler.pkl                    # From Colab bundle (you download this)
models/segment_map.json              # From Colab bundle (you download this)
models/cluster_rfm_profile.csv       # From Colab bundle (you download this)
frontend/analysis.php                # Main analysis page (States 1, 2, 3)
frontend/api/groq-insight.php        # Groq proxy + rate limiter
frontend/api/download-report.php     # PDF download proxy
frontend/assets/js/analysis.js       # All frontend JS for the analysis page
frontend/assets/css/analysis.css     # Styles for analysis page and Groq profile card
```

---

## Definition of done

The feature is complete when:

1. A user can upload any valid transaction CSV on the analysis page
2. The column mapping table auto-suggests mappings and prevents submission without the three required fields
3. The loading overlay appears with animated progress and cycling messages during pipeline execution
4. The dashboard renders with all segment cards, charts, KPI bar, validation panel, and SHAP bar
5. Clicking "Get AI Profile" on any segment card renders a structured profile card with: who they are (occupation, income, age, lifestyle), buying personality type, three advertiser signals, churn risk level with reason, and a growth opportunity action
6. The profile card headline reads like an ad agency tagline, not a data description
7. After 5 Groq requests in 10 minutes, all "Get AI Profile" buttons are disabled with a visible countdown timer
8. A server-side 429 is returned and displayed gracefully if 3+ requests come within 60 seconds from the same session
9. Clicking "Download full report PDF" downloads the complete PDF report generated by the pipeline
10. The entire flow works on mobile without horizontal scrolling
11. All errors (bad CSV, pipeline failure, Groq API failure, rate limit hit) are surfaced to the user with a clear inline message
