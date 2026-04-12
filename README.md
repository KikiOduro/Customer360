# Customer360

**Customer Segmentation System for Ghanaian SMEs**

Customer360 is a web-based platform that helps small and medium enterprises analyze their customer transaction data to discover valuable customer segments and receive actionable marketing recommendations.

![Python](https://img.shields.io/badge/Python-3.9+-blue.svg)
![FastAPI](https://img.shields.io/badge/FastAPI-0.109-green.svg)
![License](https://img.shields.io/badge/License-MIT-yellow.svg)

## Features

- **RFM Analysis**: Compute Recency, Frequency, and Monetary metrics for each customer
- **Smart Segmentation**: Multiple clustering algorithms (K-Means, GMM, Hierarchical)
- **Actionable Insights**: Plain-English segment labels with marketing recommendations
- **PDF Reports**: Professional downloadable reports for stakeholders
- **Secure**: Password hashing, JWT authentication, per-user data isolation
- **Fast**: Background job processing for large datasets

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Presentation Layer                           │
│                   (HTML/CSS/JavaScript)                         │
│         Login │ Upload │ Dashboard │ Results                    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Application Layer                            │
│                     (FastAPI Backend)                           │
│    /auth │ /upload │ /status │ /results │ /report               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Analytics Layer                             │
│    Preprocessing → RFM → Clustering → Segmentation → Reports   │
│         (pandas, numpy, scikit-learn, reportlab)                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Storage Layer                              │
│              SQLite (users, jobs) │ File Storage                │
└─────────────────────────────────────────────────────────────────┘
```

## Project Structure

```
customer360/
├── backend/
│   ├── app/
│   │   ├── main.py              # FastAPI application entry point
│   │   ├── config.py            # Configuration settings
│   │   ├── database.py          # Database connection
│   │   ├── models.py            # SQLAlchemy models
│   │   ├── schemas.py           # Pydantic schemas
│   │   ├── auth.py              # Authentication utilities
│   │   ├── report.py            # PDF report generation
│   │   ├── analytics/
│   │   │   ├── preprocessing.py # Data cleaning & validation
│   │   │   ├── rfm.py           # RFM computation
│   │   │   ├── clustering.py    # Clustering algorithms
│   │   │   ├── segmentation.py  # Segment labeling
│   │   │   └── pipeline.py      # Main analysis pipeline
│   │   └── routes/
│   │       ├── auth.py          # Auth endpoints
│   │       └── jobs.py          # Job management endpoints
│   ├── data/                    # Data storage (auto-created)
│   │   ├── uploads/
│   │   ├── outputs/
│   │   └── db/
│   ├── tests/
│   │   ├── test_rfm.py
│   │   ├── test_clustering.py
│   │   └── test_api.py
│   └── requirements.txt
├── frontend/
│   ├── index.html
│   ├── css/
│   │   └── styles.css
│   └── js/
│       ├── api.js
│       ├── auth.js
│       ├── upload.js
│       ├── dashboard.js
│       └── app.js
└── sample_data/
    └── transactions.csv
```

## Quick Start

### Prerequisites

- Python 3.9 or higher
- pip (Python package manager)

### Step 1: Navigate to the project

```bash
cd customer360
```

### Step 2: Set Up Backend

```bash
cd backend

python3 -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

pip install -r requirements.txt
```

### Step 3: Run the Backend Server

```bash
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

The API will be available at: http://localhost:8000

- **API Documentation**: http://localhost:8000/docs
- **Health Check**: http://localhost:8000/health

### Step 4: Serve the Frontend

Open a new terminal:

```bash
cd customer360/frontend

# Option 1: Python's built-in server
python3 -m http.server 3000

# Option 2: If you have Node.js
npx serve -p 3000
```

Access the application at: http://localhost:3000

## CSV Data Format

Your CSV file should contain these columns:

| Column | Description | Required |
|--------|-------------|----------|
| `customer_id` | Unique customer identifier | Yes |
| `invoice_date` | Date of transaction | Yes |
| `invoice_id` | Transaction/invoice number | Yes |
| `amount` | Transaction amount | Yes |
| `product` | Product name | Optional |
| `category` | Product category | Optional |

**Supported date formats:** `YYYY-MM-DD`, `DD-MM-YYYY`, `DD/MM/YYYY`, `MM/DD/YYYY`

### Sample Data

```csv
customer_id,invoice_date,invoice_id,amount
CUST001,2025-01-15,INV001,150.00
CUST001,2025-02-20,INV002,200.00
CUST002,2025-01-10,INV003,500.00
```

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/login` | Login (OAuth2 form) |
| POST | `/api/auth/login/json` | Login (JSON body) |
| GET | `/api/auth/me` | Get current user |

### Jobs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/jobs/` | List all jobs |
| POST | `/api/jobs/upload` | Upload CSV and start analysis |
| POST | `/api/jobs/upload/preview` | Preview CSV for column mapping |
| GET | `/api/jobs/status/{job_id}` | Get job status |
| GET | `/api/jobs/results/{job_id}` | Get job results |
| GET | `/api/jobs/report/{job_id}` | Download PDF report |
| DELETE | `/api/jobs/{job_id}` | Delete a job |

## Running Tests

```bash
cd customer360/backend

pytest          # Run all tests
pytest -v       # Verbose output
pytest tests/test_rfm.py  # Run specific test file
pytest --cov=app --cov-report=html  # With coverage
```

## Customer Segments

| Segment | Description | Typical Actions |
|---------|-------------|-----------------|
| **Champions** | Best customers | VIP treatment, referral programs |
| **Loyal Customers** | Regular high-value | Upsell, loyalty rewards |
| **Potential Loyalists** | Recent promising | Membership programs |
| **New Customers** | Recently acquired | Onboarding, first-purchase offers |
| **Promising** | Recent moderate | Targeted promotions |
| **Need Attention** | Declining engagement | Win-back offers, feedback |
| **About to Sleep** | Haven't purchased recently | Reactivation campaigns |
| **At Risk** | Previously valuable, now inactive | Urgent win-back |
| **Hibernating** | Long-inactive | Periodic reactivation |
| **Lost Customers** | Very long inactive | Final attempts, cost analysis |

## Security

- **Password Hashing**: Bcrypt with salt
- **JWT Tokens**: Secure session management
- **Per-User Isolation**: Users only see their own data
- **Input Validation**: Pydantic schemas
- **File Type Validation**: Only CSV files accepted

## Environment Variables

Create a `.env` file in `customer360/backend/`:

```env
SECRET_KEY=your-secret-key-here-change-in-production
DATABASE_URL=sqlite:///./data/db/customer360.db
```

## License

MIT License - See LICENSE file for details.
