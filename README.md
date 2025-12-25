# Product Requirements Document (PRD)
## Multi-Unit Inventory & Sales Management System

---

## 1. Product Overview

The system is a centralized inventory and point-of-sale (POS) platform designed to manage multiple operational units such as **Bar, Supermarket, Club, and Football Pitch** within the same organization.

The system enforces strict staff-to-unit access control, tracks inventory per unit, manages sales and payments, and generates automated end-of-day operational and financial reports.

---

## 2. Goals & Objectives

### 2.1 Primary Goals
- Prevent unauthorized staff access to operational units
- Track inventory movement by unit, product, and brand
- Enable fast and accurate sales processing
- Provide transparent daily operational and financial reports

### 2.2 Success Metrics
- Zero unauthorized unit operations
- Accurate daily stock reconciliation
- Reduced checkout time at POS
- Reliable end-of-day reporting

---

## 3. User Roles & Permissions

### 3.1 Super Admin
- Create and manage units
- Create and manage staff accounts
- Assign and reassign staff to units
- Manage products, brands, categories, and pricing
- View system-wide reports
- Perform administrative overrides (logged)

### 3.2 Unit Manager
- Manage inventory for assigned unit
- View unit-specific reports
- Assign staff to duty shifts
- Approve stock adjustments
- Monitor staff performance

### 3.3 Staff (Sales / Operations)
- Access only assigned unit(s)
- Perform sales transactions
- Scan products and generate invoices
- View personal shift sales data

### 3.4 Accountant / Auditor (Optional)
- View financial and sales reports
- Export data for accounting
- No inventory or sales permissions

---

## 4. Units & Staff Assignment

### 4.1 Units
Each unit (e.g., Bar, Supermarket, Club, Football Pitch) has:
- Unique identifier
- Dedicated inventory
- Pricing configuration (optional)
- Operating hours

### 4.2 Staff Assignment Rules
- A staff member must be assigned to at least one unit
- Staff cannot operate in unassigned units
- Staff can be reassigned temporarily or permanently
- Access is enforced at login and POS transaction level

---

## 5. Product & Inventory Management

### 5.1 Product Structure
Each product record includes:
- Product name
- Brand
- Category
- SKU / Barcode
- Unit of measurement (Bottle, Pack, Piece, etc.)
- Cost price
- Selling price
- Expiry date (optional)
- Trackable quantity

### 5.2 Brand Management
- Brand name
- Brand category
- One brand can have multiple products

### 5.3 Inventory Tracking
- Inventory is unit-specific
- Supported stock actions:
  - Stock In
  - Stock Out
  - Unit-to-unit transfer
  - Damaged/Lost items
- Automatic stock deduction on sale
- Low-stock alerts based on thresholds

---

## 6. Sales & POS Module

### 6.1 Sales Workflow
1. Staff logs in
2. System validates assigned unit
3. Product scanned or searched
4. Product added to cart
5. System calculates total, tax, and discounts
6. Invoice generated

### 6.2 Payment Methods
- Cash
- Transfer

#### Transfer Payment
- System generates:
  - Unique account number or reference
  - Transaction reference ID
- Payment statuses:
  - Pending
  - Confirmed
- Sale is completed after payment confirmation

---

## 7. Invoice & Receipt Management

### 7.1 Invoice
Invoice includes:
- Invoice number
- Date and time
- Unit name
- Staff on duty
- Itemized product list
- Total amount
- Payment method
- Payment status

### 7.2 Receipt
- Printable (POS printer compatible)
- Contains transaction reference or QR code
- Reprint allowed with audit log

---

## 8. Daily Operations & Reporting

### 8.1 End-of-Day Report (Auto Generated)
Includes:
- Unit name
- Date
- Staff on duty
- Total sales
- Cash total
- Transfer total
- Number of invoices
- Opening and closing stock
- Stock discrepancies

### 8.2 Report Types
- Daily sales report
- Staff performance report
- Inventory movement report
- Brand-wise sales report
- Unit-wise profitability report

---

## 9. Audit & Activity Logs

All critical actions must be logged:
- Staff login/logout
- Inventory adjustments
- Price changes
- Invoice voids or refunds

Each log entry includes:
- User
- Timestamp
- Action performed
- Affected records

---

## 10. Security & Access Control

- Role-based access control (RBAC)
- Unit-level permission enforcement
- Session timeout
- Optional POS PIN or biometric login
- Logged administrative overrides

---

## 11. System Notifications

- Low stock alerts
- Failed or pending transfer payment alerts
- End-of-day report notifications
- Unauthorized access attempt alerts

---

## 12. Non-Functional Requirements

### Performance
- Sales transactions must complete within 2 seconds
- Support concurrent POS terminals

### Reliability
- Automatic backups
- Optional offline mode with sync

### Scalability
- Add new units without downtime
- Handle large product and transaction volumes

### Compatibility
- Web-based system (desktop & tablet)
- Support barcode scanners and receipt printers

---

## 13. Future Enhancements (Phase 2)

- Mobile app for managers
- Customer accounts and loyalty program
- Automated bank transfer verification
- Accounting software integration
- Demand forecasting and analytics
- Multi-branch organization support

---

## 14. Assumptions & Constraints

- All products are barcoded
- Internet connectivity is available (unless offline mode is enabled)
- Transfer payment confirmation may initially be manual

---

## 15. Out of Scope (Phase 1)

- Online ordering
- Customer-facing mobile apps
- Third-party delivery integrations
