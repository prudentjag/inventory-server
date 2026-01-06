# Inventory Manager Dashboard (Stockist Portal)

## Product Requirements Document (PRD)

**Version:** 1.0
**Prepared by:** iPyramidTech
**Date:** Current

---

### 1. Overview

The Inventory Manager Dashboard is a centralized interface designed to enable warehouse/stock officers to efficiently manage product stock levels, warehouse operations, supply tracking, and movement of goods in and out. It ensures real-time visibility and reporting to reduce stockouts, overstocking, and losses.

---

### 2. Objectives

* Improve stock accuracy and traceability.
* Provide real-time metrics on item availability.
* Streamline supply orders and approvals.
* Provide reporting tools for forecasting and reconciliation.
* Ensure accountability via audit logs and role-based actions.

---

### 3. Key Users

| Role              | Responsibilities                                 |
| ----------------- | ------------------------------------------------ |
| Inventory Manager | Manage stock, approve requests, generate reports |
| Storekeeper       | Update physical stock movements                  |
| Management        | View insights and high-level reports             |

---

### 4. Core Features & Requirements

#### A. Dashboard (Home View)

* Real-time stock summary (Available, Low, Out-of-Stock)
* Top fast-moving & slow-moving products
* Alerts:

  * Low stock threshold
  * Expiring/expired items (if applicable)
  * Pending approvals
* Warehouse activity timeline

#### B. Stock Management

| Feature                 | Description                          |
| ----------------------- | ------------------------------------ |
| View Stock Items        | List by categories + search/filter   |
| Add New Stock           | Manual or bulk import (CSV/Excel)    |
| Update Stock Levels     | Stock In / Stock Out records         |
| SKU & Barcode support   | Barcode scanning for entry/dispatch  |
| Multi-Warehouse Support | Manage multiple stores / branches    |
| Batch Tracking          | Lot number, expiry handling optional |

#### C. Stock Movement / Transactions

* Issue stock to departments, outlets, or sales floor
* Record internal transfers
* Track goods returns & damaged goods
* Auto-update warehouse records
* Approval workflow for large quantity requests

#### D. Purchase & Restocking

* Create & manage Purchase Orders
* Vendor management database
* Goods Received Note (GRN) process
* Compare ordered vs. received quantities

#### E. Analytics & Reporting

* Stock valuation by cost
* Aging report (how long items remain unsold)
* Sales usage trends (if integrated with POS)
* Download reports (PDF, Excel)

#### F. User Access Control

* Permissions based on roles:

  * Admin: Full access
  * Manager: Stock control + approvals
  * Storekeeper: Update stock only
* Audit logs on every action

#### G. Integrations

* POS / Sales system (optional)
* Barcode printer / Scanner devices
* External finance/ERP tools (future scope)

---

### 5. Non-Functional Requirements

| Requirement           | Description                                            |
| --------------------- | ------------------------------------------------------ |
| Performance           | Dashboard loads under 3 seconds                        |
| Security              | Role-based access, encrypted data                      |
| Backup & Recovery     | Automated daily backups                                |
| Scalability           | Supports multiple warehouses and >50,000 stock records |
| Mobile Responsiveness | Mobile-friendly for warehouse operations               |

---

### 6. Success Metrics (KPIs)

* Reduce stock loss by **≥20%** after deployment
* Stockout events reduced by **≥40%**
* Inventory update accuracy **≥95%**
* Faster reconciliation — monthly count time reduced by **≥30%**

---

### 7. Assumptions

* All warehouses use barcodes or are willing to adopt them
* Storekeepers have access to mobile or desktop devices
* POS integration planned for Phase 2 (if retail)

---

### 8. Roadmap

| Phase   | Features                                                     |
| ------- | ------------------------------------------------------------ |
| Phase 1 | Core dashboard, stock CRUD, movements, alerts, basic reports |
| Phase 2 | Purchase orders, vendor mgmt, POS integration                |
| Phase 3 | AI forecasting, mobile app, offline mode                     |

---

### 9. Design References

* Clean card-based dashboard layout
* Color-coded status indicators for stock health
* Exportable tables with advanced filtering

---
