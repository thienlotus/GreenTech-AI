# GreenTech-AI: Intelligent Agricultural Pest Monitoring Platform

[![Python](https://img.shields.io/badge/Python-3.9+-blue.svg?style=flat-square&logo=python)](https://www.python.org/)
[![YOLOv11](https://img.shields.io/badge/YOLO-v11%20Medium-green.svg?style=flat-square)](https://github.com/ultralytics/ultralytics)
[![OpenCV](https://img.shields.io/badge/OpenCV-Computer%20Vision-red.svg?style=flat-square&logo=opencv)](https://opencv.org/)
[![PHP](https://img.shields.io/badge/Web%20Tier-PHP%208.2-purple.svg?style=flat-square&logo=php)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/Database-MySQL%208.0-orange.svg?style=flat-square&logo=mysql)](https://www.mysql.com/)
[![Docker](https://img.shields.io/badge/DevOps-Docker%20Compose-2496ED.svg?style=flat-square&logo=docker)](https://www.docker.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square)](LICENSE)

> Production-ready, edge-compatible Computer Vision platform utilizing fine-tuned YOLOv11 Medium and OpenCV to detect, track, and alert against 5 common agricultural pest species with an automated 4-level risk warning engine.

---

## 1. System Architecture Overview

Following Clean Architecture standards, the solution segregates computer vision inference, user workflow presentations, and persistence schemas into distinct boundaries.

```mermaid
flowchart TD
    Edge_Stream["Camera Stream / Field Images"]

    subgraph AI_Core["src/ai_service: Computer Vision Engine (Port 7860)"]
        Preprocess["Image Normalization & Letterbox"]
        YOLO["YOLOv11 Medium Detection Backbone"]
        Thresholding["Dynamic Class-Specific Threshold Filter"]
        TrackingEngine["Multi-Object Trajectory Tracker"]
        AlertClassifier["4-Level Risk Engine (Green/Yellow/Orange/Red)"]
        REST_Endpoints["Flask / FastAPI Inference Server"]
    end

    subgraph Web_App["src/web: Dashboard & Application Tier (Port 8080)"]
        Auth["Authentication & User Roles"]
        FarmerPortal["Commercial Farmer Analytics Portal"]
        FamilyPortal["Family Garden Monitoring Interface"]
        TrendReports["Pest Infestation Historical Reports"]
    end

    subgraph DB["src/database: Storage Tier (Port 3306)"]
        MySQL[("MySQL Database<br/>• Outbreak Coordinates<br/>• User Profiles<br/>• Surveillance Logs")]
    end

    Edge_Stream --> Preprocess
    Preprocess --> YOLO
    YOLO --> Thresholding
    Thresholding --> TrackingEngine
    TrackingEngine --> AlertClassifier
    AlertClassifier --> REST_Endpoints

    REST_Endpoints <-->|REST API / JSON| Web_App
    Web_App <--> DB
```

Detailed architectural specifications and mathematical formulations are documented in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).  
Student scientific research (NCKH) methodology and experimental benchmarks are documented in [docs/RESEARCH_PAPER.md](docs/RESEARCH_PAPER.md).

---

## 2. Core Features & Capabilities

1. **Precision Pest Classification (YOLOv11 Medium):**
   * Real-time inference across 5 high-impact agricultural pest species in Vietnam:
     * **Rệp mềm (Aphids)**
     * **Bọ cánh cứng (Beetles)**
     * **Châu chấu (Grasshoppers)**
     * **Ốc sên (Snails)**
     * **Sâu hại (Caterpillars / Larvae)**
2. **Automated 4-Level Outbreak Early Warning Engine:**
   * **Level 1: Xanh (Safe):** $\le 2$ pests detected; regular surveillance cycle.
   * **Level 2: Vàng (Caution):** $3 - 5$ pests detected; elevated sampling recommended.
   * **Level 3: Cam (Warning):** $6 - 10$ pests detected; localized biological treatment alert.
   * **Level 4: Đỏ (Critical):** $> 10$ pests detected; emergency containment quarantine alert.
3. **Dual Stakeholder Dashboards:**
   * **Farmer Dashboard:** Large-scale acreage tracking, chemical/biological treatment recommendation ledger, and epidemic heatmaps.
   * **Family Garden Dashboard:** Streamlined user experience for urban farming, greenhouse, and rooftop balcony garden management.

---

## 3. Repository Structure

```text
GreenTech-AI/
├── src/
│   ├── ai_service/             # YOLOv11 Computer Vision inference pipeline
│   │   ├── api_model.py        # Model loader, tracking & REST endpoints
│   │   ├── export_utils.py     # CSV/JSON export utilities
│   │   └── requirements.txt    # Python dependencies (Torch, Ultralytics, OpenCV)
│   ├── web/                    # Presentation and business workflow layer
│   │   ├── index.php           # Unified application portal
│   │   ├── dashboard_*.php     # Specialized farmer & family garden portals
│   │   ├── thong_ke.php        # Real-time analytics & charts
│   │   ├── process_*.php       # Request handlers & async live processors
│   │   ├── ai_config.php       # AI backend connectivity settings
│   │   ├── db_connect.php      # Database connection pooling
│   │   └── pest_translate.php  # Scientific nomenclature mapper
│   └── database/               # Relational database schemas
│       └── nckh_giam_sat_dich_hai.sql
├── deploy/                     # Deployment configurations
│   ├── Dockerfile              # Python + OpenCV container definition
│   └── docker-compose.yml      # Multi-container stack (AI, Web, MySQL)
├── docs/                       # Technical & Academic documentation
│   ├── ARCHITECTURE.md         # Full Clean Architecture documentation
│   └── RESEARCH_PAPER.md       # Student scientific research paper summary
├── .env.example
├── .gitignore
├── LICENSE
└── README.md
```

---

## 4. Quickstart Guide

### Running with Docker Compose (Recommended)

```bash
# Clone the repository
git clone https://github.com/thienlotus/GreenTech-AI.git
cd GreenTech-AI

# Launch the entire multi-container ecosystem
cd deploy
docker-compose up -d --build
```

* **Web Application Portal:** `http://localhost:8080`
* **AI Model Inference Server:** `http://localhost:7860`
* **MySQL Database:** `localhost:3306`

### Running the Python AI Inference Engine Locally

```bash
cd src/ai_service
python -m venv venv
# On Windows:
.\venv\Scripts\activate
# On Linux/macOS:
source venv/bin/activate

pip install -r requirements.txt
python api_model.py
```

---

## 5. Author & Academic Attribution

* **Lê Vũ Thiên** (`@thienlotus`)
* AI & Software Engineering Student | Focus: Computer Vision, Clean Architecture & Enterprise Backend
* Location: Hanoi, Vietnam
* Email: [thienchip1310@gmail.com](mailto:thienchip1310@gmail.com)
* GitHub: [https://github.com/thienlotus](https://github.com/thienlotus)

---

## 6. License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
