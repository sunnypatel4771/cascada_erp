# FE-SAT CFDI Cancellation Feature

## Overview
This document describes the CFDI invoice cancellation feature implemented for the FE-SAT module using DigiBox API.

## ✅ Implementation Complete

All cancellation functionality has been successfully implemented and is ready for testing.

---

## Features Implemented

### 1. **Database Schema** ✓
- Added cancellation tracking columns to `tblfe_sat_docs` table
- Fields: `cancellation_status`, `cancellation_date`, `cancellation_motivo`, `cancellation_folio_sustitucion`, `cancellation_acuse`
- Migration script: `upgrade_cancellation.sql`
- Migration runner: `run_cancellation_migration.php` (already executed)

### 2. **API Integration** ✓
- **File:** [modules/perfex_fesat/libraries/Fe_sat_digibox_client.php](modules/perfex_fesat/libraries/Fe_sat_digibox_client.php)
- **Method:** `cancelar()` - Uses ONLY the documented DigiBox API
- **Endpoint:** `https://testtimbrado.digibox.com.mx/api/cancelacioncfdi/cancelarcsdv2` (test)
- **Endpoint:** `https://timbrado.digibox.com.mx/api/cancelacioncfdi/cancelarcsdv2` (production)
- **Features:**
  - Authenticates with DigiBox
  - Validates motivo and folioSustitucion
  - Sends cancellation request with CSD certificates (base64 encoded)
  - Logs all API calls for debugging
  - Returns detailed success/error responses
- **Note:** All fallback/alternative API methods have been removed to strictly follow DigiBox documentation

### 3. **Controller Methods** ✓
- **File:** [modules/perfex_fesat/controllers/FeSat.php](modules/perfex_fesat/controllers/FeSat.php)
- **Methods:**
  - `cancel_form($invoiceId)` - Shows cancellation form
  - `cancel($invoiceId)` - Processes cancellation
- **Routes:**
  - `admin/fe_sat/cancel_form/{invoice_id}` - Cancellation form
  - `admin/fe_sat/cancel/{invoice_id}` - Submit cancellation

### 4. **Business Logic** ✓
- **File:** [modules/perfex_fesat/models/Fe_sat_model.php](modules/perfex_fesat/models/Fe_sat_model.php)
- **Method:** `cancelar()`
- **Features:**
  - Validates document can be cancelled
  - Validates motivo (01, 02, 03, 04)
  - Validates folioSustitucion for motivo 01
  - Loads CSD certificates from settings (REQUIRED)
  - Converts CSD files to base64 (handles both PEM and DER formats)
  - Validates base64 encoding and adds proper padding
  - Calls DigiBox CancelarCSDV2 API
  - Updates database with cancellation status
  - Logs activity
- **Important:** CSD certificate files are now REQUIRED (no fallback method)

### 5. **User Interface** ✓

#### Cancellation Form
- **File:** [modules/perfex_fesat/views/cancel_form.php](modules/perfex_fesat/views/cancel_form.php)
- **Features:**
  - Invoice information display
  - Motivo dropdown (4 options)
  - Conditional folio_sustitucion field (shows only for motivo 01)
  - Confirmation checkbox
  - JavaScript validation
  - Responsive design

#### Cancel Button in Invoice Panel
- **File:** [modules/perfex_fesat/views/partials/fe_files_panel.php](modules/perfex_fesat/views/partials/fe_files_panel.php)
- **Features:**
  - "Cancel CFDI" button (red/danger style)
  - Only shows for successfully stamped invoices
  - Hidden if already cancelled or pending
  - Displays cancellation status badges
  - Permission-based visibility

### 6. **Language Support** ✓
- **English:** [modules/perfex_fesat/language/english/fe_sat_lang.php](modules/perfex_fesat/language/english/fe_sat_lang.php)
- **Spanish:** [modules/perfex_fesat/language/spanish/fe_sat_lang.php](modules/perfex_fesat/language/spanish/fe_sat_lang.php)
- **Translations:** 25 new language strings for cancellation

---

## Cancellation Reasons (Motivos)

According to SAT regulations (CFDI 4.0), there are 4 valid cancellation reasons:

| Code | Spanish | English | Requires Folio Sustitución |
|------|---------|---------|---------------------------|
| 01 | Comprobante con errores, con relación | Invoice with errors, with relation | ✅ YES |
| 02 | Comprobante con errores, sin relación | Invoice with errors, without relation | ❌ NO |
| 03 | No se llevó a cabo la operación | Operation not carried out | ❌ NO |
| 04 | Operación nominativa en factura global | Nominative operation in global invoice | ❌ NO |

---

## User Flow

### Step 1: View Invoice
- User navigates to invoice detail page
- FE-SAT panel shows stamped CFDI information
- "Cancel CFDI" button is visible (if eligible)

### Step 2: Click Cancel Button
- User clicks "Cancel CFDI" button
- Redirected to cancellation form

### Step 3: Fill Cancellation Form
1. Select cancellation reason (motivo)
2. If motivo = 01, enter replacement UUID
3. Check confirmation checkbox
4. Click "Request Cancellation"

### Step 4: Confirmation Dialog
- JavaScript confirms action
- User confirms cancellation

### Step 5: Process Cancellation
- System validates data
- Loads CSD certificates
- Calls DigiBox API
- Updates database
- Shows success/error message

### Step 6: View Status
- Invoice panel shows cancellation status badge
- Cancel button is hidden
- Status options: Pending, Cancelled, Rejected

---

## Technical Requirements

### CSD Certificate Files
Cancellation requires **CSD (Certificado de Sello Digital)** files:

1. **`.cer` file** - Certificate file (base64 encoded)
2. **`.key` file** - Private key file (base64 encoded)
3. **Password** - CSD password (plain text)

### Where to Configure CSD Files

**Option 1: Settings Page (TODO)**
Add these fields to module settings:
```php
- perfex_fesat_csd_cer_path  // Path to .cer file
- perfex_fesat_csd_key_path  // Path to .key file
- perfex_fesat_csd_password  // CSD password
```

**Option 2: Per-Cancellation Upload (TODO)**
Add file upload fields to cancellation form

**Current Implementation:**
The model reads from settings options (lines 474-476):
```php
$csdCerPath = get_option('perfex_fesat_csd_cer_path');
$csdKeyPath = get_option('perfex_fesat_csd_key_path');
$csdPassword = get_option('perfex_fesat_csd_password');
```

---

## API Details

### DigiBox Cancellation Endpoint

**Test URL:**
```
https://testtimbrado.digibox.com.mx/api/cancelacioncfdi/cancelarcsdv2
```

**Production URL:**
```
https://timbrado.digibox.com.mx/api/cancelacioncfdi/cancelarcsdv2
```

### Request Headers

```
POST /api/cancelacioncfdi/cancelarcsdv2
Headers:
  token: {authentication_token}
  csdcer: {base64_encoded_cer_file}
  csdkey: {base64_encoded_key_file}
  password: {csd_password}
  rfcemisor: {company_rfc}
  uuids: {invoice_uuid}
  motivo: {01|02|03|04}
  foliosustitucion: {replacement_uuid} (only for motivo 01)
```

### Response Codes

| Code | Description |
|------|-------------|
| 200 | Success - Returns XML acuse |
| 201 | Successfully received for cancellation |
| 202 | Previously received for cancellation |
| 203 | UUID doesn't belong to issuer |
| 204 | UUID not applicable for cancellation |
| 205 | UUID doesn't exist |
| 207 | Invalid or missing motivo |
| 208 | Invalid folio sustitución |
| 209 | Folio sustitución not required |
| 300-310 | Authentication/certificate errors |

---

## Testing Checklist

### Prerequisites
- [ ] CSD certificates are configured
- [ ] At least one successfully stamped invoice exists
- [ ] User has `generate` permission for `fe_sat`

### Test Cases

#### 1. View Cancel Button
- [ ] Button visible on successfully stamped invoice
- [ ] Button hidden if already cancelled
- [ ] Button hidden if no UUID
- [ ] Button requires correct permission

#### 2. Cancellation Form
- [ ] Form loads with invoice information
- [ ] Motivo dropdown works
- [ ] Folio sustitución shows/hides based on motivo
- [ ] JavaScript validation works
- [ ] Confirmation checkbox required

#### 3. Submit Cancellation

**Motivo 02 (No folio required):**
- [ ] Submit without folio sustitución
- [ ] Receives success response
- [ ] Database updates with status='pending'
- [ ] Button becomes hidden
- [ ] Status badge appears

**Motivo 01 (Folio required):**
- [ ] Submit without folio → Shows error
- [ ] Submit with valid folio → Success
- [ ] Database stores folio sustitución

#### 4. Error Handling
- [ ] Missing CSD files → Clear error message
- [ ] Invalid motivo → Validation error
- [ ] Authentication failure → Shows DigiBox error
- [ ] Network error → Handled gracefully

#### 5. Status Display
- [ ] Pending status shows yellow badge
- [ ] Cancelled status shows red badge
- [ ] Rejected status shows red badge
- [ ] Status persists after page reload

---

## File Changes Summary

### New Files Created
1. `modules/perfex_fesat/views/cancel_form.php` - Cancellation form view
2. `modules/perfex_fesat/language/spanish/fe_sat_lang.php` - Spanish translations
3. `modules/perfex_fesat/upgrade_cancellation.sql` - Database migration
4. `run_cancellation_migration.php` - Migration runner
5. `modules/perfex_fesat/CANCELLATION_FEATURE.md` - This documentation

### Modified Files
1. `modules/perfex_fesat/libraries/Fe_sat_digibox_client.php` - Added cancelar() method
2. `modules/perfex_fesat/controllers/FeSat.php` - Added cancel routes
3. `modules/perfex_fesat/models/Fe_sat_model.php` - Added cancelar() business logic
4. `modules/perfex_fesat/views/partials/fe_files_panel.php` - Added cancel button
5. `modules/perfex_fesat/language/english/fe_sat_lang.php` - Added English strings
6. `modules/perfex_fesat/config/routes.php` - Added module-level routes
7. `application/config/routes.php` - **IMPORTANT:** Added admin routes for cancellation

---

## Deployment Instructions

### For Development/Testing
1. ✅ Database migration already run
2. All files are in the module folder
3. Ready to test immediately

### For Production Deployment
1. Copy entire `modules/perfex_fesat/` folder to other sites
2. **IMPORTANT:** Add these routes to `application/config/routes.php` (around line 180):
   ```php
   $route['admin/fe_sat/cancel-form/(:num)']  = 'perfex_fesat/FeSat/cancel_form/$1';
   $route['admin/fe_sat/cancel/(:num)']       = 'perfex_fesat/FeSat/cancel/$1';
   ```
   Place them after the existing fe_sat routes.
3. Run `run_cancellation_migration.php` on each site:
   ```bash
   cd /path/to/perfex
   php run_cancellation_migration.php
   ```
4. Configure CSD certificate files in settings
5. Test cancellation on a test invoice first

### Rollback (if needed)
```sql
ALTER TABLE `tblfe_sat_docs`
DROP COLUMN `cancellation_status`,
DROP COLUMN `cancellation_date`,
DROP COLUMN `cancellation_motivo`,
DROP COLUMN `cancellation_folio_sustitucion`,
DROP COLUMN `cancellation_acuse`,
DROP KEY `cancellation_status_idx`;
```

---

## Next Steps (Optional Enhancements)

1. **Add CSD Upload to Settings Page**
   - File upload fields for .cer and .key
   - Password input field
   - Store securely in database or uploads folder

2. **Cancellation Status Tracking**
   - Implement webhook to receive SAT responses
   - Auto-update status from 'pending' to 'cancelled'
   - Email notification on cancellation

3. **Batch Cancellation**
   - Select multiple invoices
   - Cancel all with same motivo
   - Bulk status update

4. **Audit Log**
   - Track who cancelled what and when
   - Show cancellation history
   - Export cancellation report

5. **Production Mode Toggle**
   - Switch between test/production endpoints
   - Based on sandbox setting

---

## Support & Documentation

### DigiBox API Documentation
- File: `media/Esquema de cancelación API.pdf`
- File: `media/Ejemplo Cancelacion.postman_collection.json`

### DigiBox Support
- **Phone:** (33) 3854.3949 or 01.800.1344.269
- **Email:** soporte@digibox.com.mx

### SAT Regulations
- CFDI 4.0 Cancellation Rules (2022+)
- Requires customer acceptance (72 hours)
- Automatic acceptance if no response

---

## Conclusion

✅ **The CFDI cancellation feature is fully implemented and ready for testing!**

All code is modular and self-contained within the `perfex_fesat` module, making it easy to deploy to multiple sites.

The implementation follows SAT regulations and DigiBox API specifications for CFDI 4.0 cancellation.
