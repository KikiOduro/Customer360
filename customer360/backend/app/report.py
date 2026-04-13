"""
PDF Report generation module for Customer360.
Uses reportlab to generate professional segmentation reports.

The generator consumes the same results dictionary shown on analytics.php, including
charts and Groq/fallback narratives, so the downloaded PDF matches the web results.
"""
from datetime import datetime
from pathlib import Path
from typing import Dict, Any, List
import io

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4, letter
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch, cm
from reportlab.lib.utils import ImageReader
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    PageBreak, Image, HRFlowable
)
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_JUSTIFY
import logging

logger = logging.getLogger(__name__)


class ReportGenerator:
    """
    Generates PDF reports for customer segmentation analysis.

    Each section is built from aggregated analytics output only; raw uploaded file
    paths are not exposed in the report.
    """
    
    def __init__(self, results: Dict[str, Any], company_name: str = ""):
        """
        Initialize the report generator.
        
        Args:
            results: Full results dictionary from segmentation pipeline
            company_name: Company name to display on report
        """
        self.results = results
        self.company_name = company_name
        self.styles = getSampleStyleSheet()
        self._setup_custom_styles()
    
    def _setup_custom_styles(self):
        """Set up custom paragraph styles for the report."""
        self.styles.add(ParagraphStyle(
            name='CustomTitle',
            parent=self.styles['Heading1'],
            fontSize=24,
            spaceAfter=30,
            textColor=colors.HexColor('#1a365d'),
            alignment=TA_CENTER
        ))
        
        self.styles.add(ParagraphStyle(
            name='SectionTitle',
            parent=self.styles['Heading2'],
            fontSize=16,
            spaceBefore=20,
            spaceAfter=12,
            textColor=colors.HexColor('#2c5282')
        ))
        
        self.styles.add(ParagraphStyle(
            name='SubSection',
            parent=self.styles['Heading3'],
            fontSize=12,
            spaceBefore=15,
            spaceAfter=8,
            textColor=colors.HexColor('#4a5568')
        ))
        
        self.styles.add(ParagraphStyle(
            name='ReportBodyText',
            parent=self.styles['Normal'],
            fontSize=10,
            spaceAfter=8,
            alignment=TA_JUSTIFY
        ))
        
        self.styles.add(ParagraphStyle(
            name='SmallText',
            parent=self.styles['Normal'],
            fontSize=8,
            textColor=colors.HexColor('#718096')
        ))

    def _currency_code(self) -> str:
        """Return a report-safe currency label that renders correctly in PDF core fonts."""
        raw_currency = str(self.results.get('meta', {}).get('currency') or 'GHS').strip()
        if not raw_currency:
            return 'GHS'

        # Avoid symbols like GH₵ because ReportLab's default Helvetica core font may not
        # render the cedi glyph consistently in generated PDFs.
        if raw_currency.upper() in {'GH₵', 'GHS', 'CEDI', 'GH¢'}:
            return 'GHS'

        ascii_currency = raw_currency.encode('ascii', 'ignore').decode('ascii').strip()
        return ascii_currency or 'GHS'

    def _safe_number(self, value: Any) -> float:
        """Coerce nullable numeric values to a finite float."""
        try:
            numeric_value = float(value or 0)
        except (TypeError, ValueError):
            return 0.0

        return numeric_value

    def _format_currency(self, amount: Any, decimals: int = 2) -> str:
        """Format monetary values with an ASCII currency code to avoid missing glyphs."""
        return f"{self._currency_code()} {self._safe_number(amount):,.{decimals}f}"

    def _report_meta(self) -> Dict[str, Any]:
        """Return report summary metadata with fallbacks for older result JSON files."""
        meta = dict(self.results.get('meta') or {})
        preprocessing_summary = self.results.get('preprocessing', {}).get('summary', {})
        clustering = self.results.get('clustering', {})

        fallback_fields = {
            'num_customers': preprocessing_summary.get('num_customers', 0),
            'num_transactions': preprocessing_summary.get('num_transactions', 0),
            'total_revenue': preprocessing_summary.get('total_revenue', 0),
            'num_clusters': clustering.get('n_clusters', 0),
            'silhouette_score': clustering.get('silhouette_score', 0),
            'clustering_method': clustering.get('method', 'kmeans'),
        }

        for key, fallback_value in fallback_fields.items():
            if meta.get(key) in (None, '', 0, 0.0):
                meta[key] = fallback_value

        return meta

    def _grouping_strength_label(self, score: Any) -> str:
        """Translate the clustering score into plain language."""
        numeric_score = self._safe_number(score)
        if numeric_score >= 0.55:
            return "strong and easy to separate"
        if numeric_score >= 0.35:
            return "fairly clear, but some customer groups still overlap"
        if numeric_score > 0:
            return "soft, meaning several customers behave in similar ways across groups"
        return "not available"

    def _segment_title(self, seg: Dict[str, Any]) -> str:
        """Return a readable segment title with a compact standard label fallback."""
        label = str(seg.get('segment_label') or 'Customer Group').strip() or 'Customer Group'
        short_name = str(seg.get('segment_short_name') or '').strip()
        if short_name and short_name != label:
            return f"{label} ({short_name})"
        return label

    def _plain_metric_name(self, metric: str) -> str:
        """Convert RFM field names to user-facing language."""
        return {
            'recency': 'Days Since Last Purchase',
            'frequency': 'Number of Purchases',
            'monetary': 'Total Customer Spend',
        }.get(metric, metric.capitalize())
    
    def generate(self, output_path: str) -> str:
        """
        Generate the PDF report.
        
        Args:
            output_path: Path where PDF should be saved
            
        Returns:
            Path to generated PDF
        """
        doc = SimpleDocTemplate(
            output_path,
            pagesize=A4,
            rightMargin=2*cm,
            leftMargin=2*cm,
            topMargin=2*cm,
            bottomMargin=2*cm
        )
        
        story = []
        
        # Title page
        story.extend(self._build_title_page())
        story.append(PageBreak())
        
        # Executive Summary
        story.extend(self._build_executive_summary())
        
        # Data Overview
        story.extend(self._build_data_overview())
        
        # RFM Analysis
        story.extend(self._build_rfm_analysis())
        
        # Segmentation Results
        story.extend(self._build_segmentation_results())

        # Visual charts and plots
        story.extend(self._build_visual_analysis())
        
        # Detailed Segment Profiles
        story.extend(self._build_segment_profiles())
        
        # Recommendations
        story.extend(self._build_recommendations())
        
        # Build PDF
        doc.build(story)
        
        logger.info(f"Report generated: {output_path}")
        return output_path
    
    def _build_title_page(self) -> List:
        """Build the title page elements."""
        elements = []
        
        elements.append(Spacer(1, 2*inch))
        
        # Title
        elements.append(Paragraph(
            "Customer Segmentation Report",
            self.styles['CustomTitle']
        ))
        
        elements.append(Spacer(1, 0.5*inch))
        
        if self.company_name:
            elements.append(Paragraph(
                f"Prepared for: {self.company_name}",
                ParagraphStyle(
                    'CompanyName',
                    parent=self.styles['Normal'],
                    fontSize=14,
                    alignment=TA_CENTER,
                    textColor=colors.HexColor('#4a5568')
                )
            ))
        
        elements.append(Spacer(1, 0.3*inch))
        
        # Date
        date_str = datetime.now().strftime("%B %d, %Y")
        elements.append(Paragraph(
            f"Generated on: {date_str}",
            ParagraphStyle(
                'Date',
                parent=self.styles['Normal'],
                fontSize=11,
                alignment=TA_CENTER,
                textColor=colors.HexColor('#718096')
            )
        ))
        
        elements.append(Spacer(1, 1*inch))
        
        # Powered by
        elements.append(Paragraph(
            "Powered by Customer360",
            ParagraphStyle(
                'PoweredBy',
                parent=self.styles['Normal'],
                fontSize=10,
                alignment=TA_CENTER,
                textColor=colors.HexColor('#a0aec0')
            )
        ))
        
        return elements
    
    def _build_executive_summary(self) -> List:
        """Build the executive summary section."""
        elements = []
        
        elements.append(Paragraph("Executive Summary", self.styles['SectionTitle']))
        elements.append(HRFlowable(width="100%", thickness=1, color=colors.HexColor('#e2e8f0')))
        elements.append(Spacer(1, 0.2*inch))
        
        meta = self._report_meta()
        summary = self.results.get('segment_summary', {})
        story_summary = self.results.get('story_summary', {}) or {}
        llm_analysis = self.results.get('llm_analysis', {}) or {}
        
        grouping_method = str(meta.get('clustering_method', 'kmeans') or 'kmeans').replace('_', ' ').title()
        grouping_strength = self._grouping_strength_label(meta.get('silhouette_score', 0))

        # Key metrics
        summary_text = f"""
        This report analyzes <b>{meta.get('num_customers', 0):,}</b> customers based on 
        <b>{meta.get('num_transactions', 0):,}</b> transactions totaling 
        <b>{self._format_currency(meta.get('total_revenue', 0))}</b> in revenue.
        <br/><br/>
        The platform grouped customers into <b>{meta.get('num_clusters', 0)}</b> customer groups by looking at
        <b>how recently they bought</b>, <b>how often they buy</b>, and <b>how much they spend</b>.
        The grouping clarity is <b>{grouping_strength}</b>. For technical review, the selected backend method was
        <b>{grouping_method}</b>, but day-to-day users can focus on the group descriptions and recommended actions below.
        """
        
        elements.append(Paragraph(summary_text, self.styles['ReportBodyText']))
        elements.append(Spacer(1, 0.3*inch))

        llm_story = str(llm_analysis.get('story') or '').strip()
        llm_headline = str(llm_analysis.get('headline') or '').strip()
        if llm_story or llm_headline:
            elements.append(Paragraph("What This Means for Your Business", self.styles['SubSection']))
            if llm_headline:
                elements.append(Paragraph(f"<b>{llm_headline}</b>", self.styles['ReportBodyText']))
            if llm_story:
                elements.append(Paragraph(llm_story, self.styles['ReportBodyText']))
            elements.append(Spacer(1, 0.2*inch))
        elif story_summary.get('narrative'):
            elements.append(Paragraph("What This Means for Your Business", self.styles['SubSection']))
            if story_summary.get('headline'):
                elements.append(Paragraph(f"<b>{story_summary.get('headline')}</b>", self.styles['ReportBodyText']))
            elements.append(Paragraph(str(story_summary.get('narrative')), self.styles['ReportBodyText']))
            elements.append(Spacer(1, 0.2*inch))
        
        # Key findings
        elements.append(Paragraph("Key Takeaways", self.styles['SubSection']))
        
        llm_findings = [
            str(finding)
            for finding in (llm_analysis.get('key_findings') or [])
            if finding
        ]
        if llm_findings:
            for finding in llm_findings[:5]:
                elements.append(Paragraph(f"• {finding}", self.styles['ReportBodyText']))
        elif summary:
            findings = [
                f"• Highest value segment: {summary.get('highest_value_segment', {}).get('label', 'N/A')} "
                f"(avg. {self._format_currency(summary.get('highest_value_segment', {}).get('avg_monetary', 0))} per customer)",
                f"• Most loyal segment: {summary.get('most_loyal_segment', {}).get('label', 'N/A')} "
                f"(avg. {summary.get('most_loyal_segment', {}).get('avg_frequency', 0):.1f} transactions)",
                f"• At-risk customers: {summary.get('at_risk_customers', 0):,} customers "
                f"representing {self._format_currency(summary.get('at_risk_revenue', 0))} in potential revenue"
            ]
            
            for finding in findings:
                elements.append(Paragraph(finding, self.styles['ReportBodyText']))
        
        elements.append(Spacer(1, 0.3*inch))
        
        return elements
    
    def _build_data_overview(self) -> List:
        """Build the data overview section."""
        elements = []
        
        elements.append(Paragraph("Data Overview", self.styles['SectionTitle']))
        elements.append(HRFlowable(width="100%", thickness=1, color=colors.HexColor('#e2e8f0')))
        elements.append(Spacer(1, 0.2*inch))
        
        preprocessing = self.results.get('preprocessing', {})
        summary = preprocessing.get('summary', {})
        date_range = preprocessing.get('date_range', {})
        cleaning_stats = preprocessing.get('cleaning_stats', {})
        
        # Data summary table
        data = [
            ['Metric', 'Value'],
            ['Total Transactions', f"{summary.get('num_transactions', 0):,}"],
            ['Unique Customers', f"{summary.get('num_customers', 0):,}"],
            ['Total Revenue', self._format_currency(summary.get('total_revenue', 0))],
            ['Average Transaction', self._format_currency(summary.get('avg_transaction', 0))],
            ['Date Range', f"{date_range.get('start', 'N/A')[:10]} to {date_range.get('end', 'N/A')[:10]}"],
            ['Data Quality', f"{cleaning_stats.get('retention_rate', 0)*100:.1f}% rows retained after cleaning"]
        ]
        
        table = Table(data, colWidths=[3*inch, 3*inch])
        table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#edf2f7')),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.HexColor('#2d3748')),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, -1), 10),
            ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
            ('PADDING', (0, 0), (-1, -1), 8),
            ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#e2e8f0')),
            ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, colors.HexColor('#f7fafc')])
        ]))
        
        elements.append(table)
        elements.append(Spacer(1, 0.3*inch))
        
        return elements
    
    def _build_rfm_analysis(self) -> List:
        """Build the RFM analysis section."""
        elements = []
        
        elements.append(Paragraph("How the Platform Grouped Your Customers", self.styles['SectionTitle']))
        elements.append(HRFlowable(width="100%", thickness=1, color=colors.HexColor('#e2e8f0')))
        elements.append(Spacer(1, 0.2*inch))
        
        elements.append(Paragraph(
            """The platform grouped customers using three simple buying signals. You do not need to know the technical model details to read this report. Just interpret the groups using these three ideas:""",
            self.styles['ReportBodyText']
        ))
        
        rfm_explanation = [
            "<b>Days Since Last Purchase</b>: customers who bought more recently are usually warmer and easier to re-engage.",
            "<b>Number of Purchases</b>: customers who come back more often are usually more loyal.",
            "<b>Total Spend</b>: customers who have spent more money usually contribute more value to the business."
        ]
        
        for item in rfm_explanation:
            elements.append(Paragraph(f"• {item}", self.styles['ReportBodyText']))
        
        elements.append(Spacer(1, 0.2*inch))
        
        # RFM statistics table
        rfm_stats = self.results.get('rfm_statistics', {})
        
        data = [['Metric', 'Mean', 'Median', 'Std Dev', 'Min', 'Max']]
        
        for metric in ['recency', 'frequency', 'monetary']:
            stats = rfm_stats.get(metric, {})
            suffix = " days" if metric == 'recency' else ""
            value_formatter = (
                lambda value: self._format_currency(value, decimals=1)
                if metric == 'monetary'
                else f"{self._safe_number(value):,.1f}{suffix}"
            )
            data.append([
                self._plain_metric_name(metric),
                value_formatter(stats.get('mean', 0)),
                value_formatter(stats.get('median', 0)),
                value_formatter(stats.get('std', 0)),
                value_formatter(stats.get('min', 0)),
                value_formatter(stats.get('max', 0))
            ])
        
        table = Table(data, colWidths=[1.2*inch, 1.2*inch, 1.2*inch, 1.2*inch, 1*inch, 1*inch])
        table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#2c5282')),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, -1), 9),
            ('ALIGN', (1, 0), (-1, -1), 'RIGHT'),
            ('ALIGN', (0, 0), (0, -1), 'LEFT'),
            ('PADDING', (0, 0), (-1, -1), 6),
            ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#e2e8f0')),
            ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, colors.HexColor('#f7fafc')])
        ]))
        
        elements.append(table)
        elements.append(Spacer(1, 0.3*inch))
        
        return elements
    
    def _build_segmentation_results(self) -> List:
        """Build the segmentation results section."""
        elements = []
        
        elements.append(Paragraph("Customer Groups Found by the Platform", self.styles['SectionTitle']))
        elements.append(HRFlowable(width="100%", thickness=1, color=colors.HexColor('#e2e8f0')))
        elements.append(Spacer(1, 0.2*inch))
        
        clustering = self.results.get('clustering', {})
        
        elements.append(Paragraph(
            f"""The platform found <b>{clustering.get('n_clusters', 0)}</b> customer groups in this dataset.
            Some groups may have similar broad names but different “Group 1 / Group 2” endings when their buying patterns are close but not identical.""",
            self.styles['ReportBodyText']
        ))
        
        elements.append(Spacer(1, 0.2*inch))
        
        # Segments overview table
        segments = self.results.get('segments', [])
        
        data = [['Segment', 'Customers', '%', 'Avg Recency', 'Avg Frequency', 'Avg Monetary', 'Total Revenue']]
        
        for seg in segments:
            data.append([
                self._segment_title(seg),
                f"{seg['num_customers']:,}",
                f"{seg['percentage']:.1f}%",
                f"{seg['avg_recency']:.0f} days",
                f"{seg['avg_frequency']:.1f}",
                self._format_currency(seg['avg_monetary']),
                self._format_currency(seg['total_revenue'])
            ])
        
        table = Table(data, colWidths=[1.3*inch, 0.8*inch, 0.6*inch, 0.9*inch, 0.9*inch, 1*inch, 1.1*inch])
        table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#2c5282')),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, -1), 8),
            ('ALIGN', (1, 0), (-1, -1), 'RIGHT'),
            ('ALIGN', (0, 0), (0, -1), 'LEFT'),
            ('PADDING', (0, 0), (-1, -1), 5),
            ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#e2e8f0')),
            ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, colors.HexColor('#f7fafc')])
        ]))
        
        elements.append(table)
        elements.append(Spacer(1, 0.3*inch))
        
        return elements

    def _build_visual_analysis(self) -> List:
        """Build a chart-heavy section from the generated analysis artifacts."""
        elements = []
        charts = self.results.get('charts') or {}

        elements.append(PageBreak())
        elements.append(Paragraph("Visual Analysis & Segment Plots", self.styles['SectionTitle']))
        elements.append(HRFlowable(width="100%", thickness=1, color=colors.HexColor('#e2e8f0')))
        elements.append(Spacer(1, 0.2 * inch))

        if not charts:
            elements.append(Paragraph(
                "No chart artifacts were attached to this report output.",
                self.styles['ReportBodyText']
            ))
            elements.append(Spacer(1, 0.2 * inch))
            return elements

        chart_sections = [
            (
                'pca_scatter',
                'Customer Segment Map',
                'Customers shown close together tend to behave in similar ways. Customers far apart behave differently. The colors show the groups the platform discovered.'
            ),
            (
                'segment_sizes',
                'Segment Size Distribution',
                'This shows how many customers fall into each group, which helps you see whether one group is very large or whether the customer base is spread out.'
            ),
            (
                'rfm_distributions',
                'Buying Pattern Distributions',
                'This shows how recent buying, repeat visits, and customer spend are spread across the dataset.'
            ),
            (
                'pareto',
                'Revenue Concentration',
                'This shows which customer groups bring in most of the revenue first, so you can see whether sales depend heavily on a small number of groups.'
            ),
            (
                'algorithm_comparison',
                'Grouping Confidence Check',
                'This compares the backend grouping methods and helps the platform choose the one that separates customers most clearly. Non-technical users do not need to choose a method manually.'
            ),
            (
                'radar_chart',
                'Group Behaviour Profile',
                'This compares whether each customer group is stronger on recent purchases, repeat buying, or total spending.'
            ),
            (
                'rfm_violin_plots',
                'Group-by-Group Differences',
                'This shows whether customers inside each group behave very similarly or whether there is still a wide spread within the group.'
            ),
        ]

        rendered_count = 0
        for chart_key, chart_title, chart_description in chart_sections:
            chart_path = charts.get(chart_key)
            if not chart_path:
                continue

            chart_file = Path(chart_path)
            if not chart_file.exists():
                logger.warning("Skipping missing chart artifact %s at %s", chart_key, chart_file)
                continue

            elements.append(Paragraph(chart_title, self.styles['SubSection']))
            elements.append(Paragraph(chart_description, self.styles['ReportBodyText']))
            elements.append(Spacer(1, 0.08 * inch))
            elements.extend(self._build_chart_figure(chart_file))
            elements.append(Spacer(1, 0.22 * inch))
            rendered_count += 1

        if rendered_count == 0:
            elements.append(Paragraph(
                "Chart paths were present in the analysis output, but none of the image files could be loaded from disk.",
                self.styles['ReportBodyText']
            ))
            elements.append(Spacer(1, 0.2 * inch))

        return elements

    def _build_chart_figure(self, chart_file: Path) -> List:
        """Return a scaled chart image block that fits inside the report page."""
        max_width = 6.7 * inch
        max_height = 4.4 * inch

        try:
            image_width, image_height = ImageReader(str(chart_file)).getSize()
            if image_width <= 0 or image_height <= 0:
                raise ValueError("Image has invalid dimensions")

            scale = min(max_width / image_width, max_height / image_height, 1.0)
            figure = Image(str(chart_file), width=image_width * scale, height=image_height * scale)
            figure.hAlign = 'CENTER'
            return [figure]
        except Exception as exc:
            logger.warning("Could not embed chart %s: %s", chart_file, exc)
            return [
                Paragraph(
                    f"Chart could not be embedded: {chart_file.name}",
                    self.styles['SmallText']
                )
            ]
    
    def _build_segment_profiles(self) -> List:
        """Build detailed segment profiles."""
        elements = []
        
        elements.append(PageBreak())
        elements.append(Paragraph("What Each Customer Group Means", self.styles['SectionTitle']))
        elements.append(HRFlowable(width="100%", thickness=1, color=colors.HexColor('#e2e8f0')))
        elements.append(Spacer(1, 0.2*inch))
        
        segments = self.results.get('segments', [])
        llm_actions = self.results.get('llm_analysis', {}).get('top_3_actions', [])

        if llm_actions:
            elements.append(Paragraph("Top Actions This Week", self.styles['SubSection']))
            for index, action_item in enumerate(llm_actions[:3], start=1):
                if not isinstance(action_item, dict):
                    continue
                action_text = (
                    f"<b>{index}. {action_item.get('action', 'Take one focused customer follow-up action')}</b><br/>"
                    f"{action_item.get('why', '')}<br/>"
                    f"{action_item.get('how', '')}"
                )
                elements.append(Paragraph(action_text, self.styles['ReportBodyText']))
            elements.append(Spacer(1, 0.25*inch))
        
        for seg in segments:
            elements.append(Paragraph(
                f"{self._segment_title(seg)} - {seg['percentage']:.1f}% of customers",
                self.styles['SubSection']
            ))

            segment_description = str(seg.get('ai_insight') or seg.get('description') or '').strip()
            if segment_description:
                elements.append(Paragraph(segment_description, self.styles['ReportBodyText']))
            
            # Segment metrics
            metrics_text = f"""
            <b>Size:</b> {seg['num_customers']:,} customers<br/>
            <b>Revenue Contribution:</b> {self._format_currency(seg['total_revenue'])}<br/>
            <b>Days Since Last Purchase:</b> {seg['avg_recency']:.0f} days on average (range: {seg['min_recency']}-{seg['max_recency']})<br/>
            <b>Number of Purchases:</b> {seg['avg_frequency']:.1f} on average (range: {seg['min_frequency']}-{seg['max_frequency']})<br/>
            <b>Average Customer Spend:</b> {self._format_currency(seg['avg_monetary'])} (range: {self._format_currency(seg['min_monetary'])}-{self._format_currency(seg['max_monetary'])})
            """
            elements.append(Paragraph(metrics_text, self.styles['ReportBodyText']))
            
            elements.append(Spacer(1, 0.1*inch))
        
        return elements
    
    def _build_recommendations(self) -> List:
        """Build the recommendations section."""
        elements = []
        
        elements.append(PageBreak())
        elements.append(Paragraph("Recommended Actions for Each Group", self.styles['SectionTitle']))
        elements.append(HRFlowable(width="100%", thickness=1, color=colors.HexColor('#e2e8f0')))
        elements.append(Spacer(1, 0.2*inch))
        
        segments = self.results.get('segments', [])
        
        for seg in segments:
            elements.append(Paragraph(
                f"<b>{self._segment_title(seg)}</b>",
                self.styles['SubSection']
            ))

            action_list = seg.get('ai_actions') or seg.get('recommended_actions') or []
            for action in action_list[:3]:  # Top 3 actions
                elements.append(Paragraph(f"• {action}", self.styles['ReportBodyText']))
            
            elements.append(Spacer(1, 0.2*inch))
        
        # Footer
        elements.append(Spacer(1, 0.5*inch))
        elements.append(HRFlowable(width="100%", thickness=1, color=colors.HexColor('#e2e8f0')))
        elements.append(Paragraph(
            "This report was generated by Customer360. For questions or support, contact your administrator.",
            self.styles['SmallText']
        ))
        
        return elements


def generate_report(
    results: Dict[str, Any],
    output_path: str,
    company_name: str = ""
) -> str:
    """
    Generate a PDF report from segmentation results.
    
    Args:
        results: Full results dictionary from segmentation pipeline
        output_path: Path where PDF should be saved
        company_name: Company name to display on report
        
    Returns:
        Path to generated PDF
    """
    generator = ReportGenerator(results, company_name)
    return generator.generate(output_path)
