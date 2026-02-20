# Cancellation Testing Checklist

## ✅ Changes Made for Testing

### 1. Hardcoded DER Certificate Files
**File:** `modules/perfex_fesat/models/Fe_sat_model.php` (lines 480-495)

```php
// HARDCODED FOR TESTING
$csdCerPath = FCPATH . 'uploads/fe_sat/csd/CSD_Sucursal_1_EKU9003173C9_20230517_223850.cer'; // DER
$csdKeyPath = FCPATH . 'uploads/fe_sat/csd/CSD_Sucursal_1_EKU9003173C9_20230517_223850.key'; // DER
$csdPassword = '12345678a';
```

**Why:** Ensures we're using DER format files and not accidentally double-encoding PEM files.

### 2. Added Detailed Logging
**Files Modified:**
- `modules/perfex_fesat/models/Fe_sat_model.php` (lines 505-548)
- `modules/perfex_fesat/libraries/Fe_sat_digibox_client.php` (lines 602-615)

**Logs to Check:**
- File size (binary)
- Base64 length (should be ~1948 chars for .cer, ~1732 chars for .key)
- Base64 preview (first 50 and last 50 chars)
- Format detection (DER vs PEM)

### 3. Verified Header Separation
**File:** `modules/perfex_fesat/libraries/Fe_sat_digibox_client.php` (lines 619-627)

```php
$headers = [
    'token: ' . trim($token),
    'csdcer: ' . $csdCerBase64,        // SEPARATE HEADER
    'csdkey: ' . $csdKeyBase64,        // SEPARATE HEADER
    'password: ' . trim($csdPassword),
    'rfcemisor: ' . strtoupper(trim($rfcEmisor)),
    'uuids: ' . trim($uuid),
    'motivo: ' . trim($motivo),
];
```

**Confirmed:** Each certificate component is sent as a SEPARATE HTTP header (not concatenated).

---

## 🧪 Pre-Testing Verification

### Step 1: Verify Test Files Exist
```bash
ls -la uploads/fe_sat/csd/
```

**Expected files:**
- ✅ `CSD_Sucursal_1_EKU9003173C9_20230517_223850.cer` (DER format, ~1,460 bytes)
- ✅ `CSD_Sucursal_1_EKU9003173C9_20230517_223850.key` (DER format, ~1,298 bytes)

### Step 2: Run Format Tests
```bash
php test_csd_formats.php > test_csd_output.html
php test_header_separation.php > test_header_output.html
```

**Expected Results:**
- ✅ DER format detected
- ✅ Base64 encoding valid
- ✅ Proper padding (length % 4 == 0)
- ✅ Headers are separate

### Step 3: Check Database
```sql
SELECT id, invoice_id, digibox_uuid, status, cancellation_status
FROM tblfe_sat_docs
WHERE status = 'success'
  AND cancellation_status IS NULL
LIMIT 5;
```

**Find an invoice UUID to test cancellation on.**

---

## 🚀 Testing Steps

### Step 1: Access Cancellation Form
1. Navigate to an invoice with a stamped CFDI
2. Click "Cancel CFDI" button in the FE-SAT panel
3. **Expected:** Form loads with invoice details

### Step 2: Fill Form
1. Select **Motivo: 02** (No se llevó a cabo la operación)
2. Leave "Folio de Sustitución" empty (not required for motivo 02)
3. Check confirmation checkbox
4. Click "Request Cancellation"

### Step 3: Monitor Logs

**Check Activity Log:**
Go to: Setup → Activity Log

**Look for these entries (in order):**
```
✓ FE-SAT Cancellation - USING HARDCODED DER FILES FOR TESTING
✓ FE-SAT Cancellation - CER Path: /path/to/file.cer
✓ FE-SAT Cancellation - KEY Path: /path/to/file.key
✓ FE-SAT Cancellation - CER file size: 1460 bytes
✓ FE-SAT Cancellation - CER detected as DER format (binary)
✓ FE-SAT Cancellation - CER base64 length: 1948 chars
✓ FE-SAT Cancellation - CER base64 preview (first 50): MIIFsDCCA5igAwIBAgIUMzAwMDEwMDAwMDA1MDAwMDM0MTYw...
✓ FE-SAT Cancellation - KEY file size: 1298 bytes
✓ FE-SAT Cancellation - KEY detected as DER format (binary)
✓ FE-SAT Cancellation - KEY base64 length: 1732 chars
✓ FE-SAT Cancellation - Calling CancelarCSDV2 API...
```

**Check PHP Error Log:**
Location: `application/logs/log-YYYY-MM-DD.php` or server error log

**Look for DigiBox debug output:**
```
===== DIGIBOX CANCELLATION REQUEST DEBUG =====
Token length: XXX chars
CSD CER base64 length: 1948 chars      ← Should be ~1948
CSD KEY base64 length: 1732 chars      ← Should be ~1732
CSD CER first 50: MIIFsDCCA5igAwIBAgIUMzAwMDEwMDAwMDA1MDAwMDM0MTYw...
CSD CER last 50: ...2yQSg4bjeDlJ08lXaaFCLW2peEXMXjQUk7fmpb5MNuOUTW6BE=
CSD KEY first 50: MIIFDjBABgkqhkiG9w0BBQ0wMzAbBgkqhkiG9w0BBQwwDgQI...
CSD KEY last 50: ...PplKp3/a5Kr5yM0T4wJoKQQ6v3vSNmrhpbuAtKxpMILe8CQoo=
Password: **********
RFC Emisor: EKU9003173C9
UUIDs: [your-uuid-here]
Motivo: 02
Folio Sustitucion: N/A
==============================================
```

---

## 📊 Expected Results

### Success Response (HTTP 200/201)
```
✓ Cancellation request sent successfully
✓ Database updated with cancellation_status = 'pending'
✓ Cancel button becomes hidden
✓ Status badge shows "Pending" in yellow
```

**DigiBox Response Codes:**
- **201** - UUID successfully received for cancellation ✅
- **202** - UUID previously received for cancellation ✅

### Common Error Responses

| Code | Error | Cause | Solution |
|------|-------|-------|----------|
| 310 | CSD Inválido | Wrong encoding, concatenated headers, or invalid certificate | Check logs for base64 lengths (~1948 and ~1732) |
| 304 | Certificado Revocado o Caduco | Certificate expired or revoked | Get new CSD from SAT |
| 305/309 | Certificado Inválido | Wrong certificate type or corrupted | Verify it's CSD (not FIEL) |
| 203 | Folio fiscal no correspondiente al emisor | UUID doesn't belong to RFC | Check RFC matches certificate |
| 207 | Motivo no válido | Invalid motivo | Use 01, 02, 03, or 04 |
| 208 | Folio Sustitución invalido | Invalid replacement UUID | Check UUID format |
| 209 | Folio Sustitución no requerido | Sent folio for motivo 02/03/04 | Don't send folio for non-01 motivos |

---

## ✅ Validation Checklist

### Before Calling API:
- [ ] CSD files exist at hardcoded paths
- [ ] Files are DER format (binary, not PEM)
- [ ] CER file is ~1,460 bytes
- [ ] KEY file is ~1,298 bytes
- [ ] Password is correct (12345678a)
- [ ] RFC matches certificate (EKU9003173C9)
- [ ] UUID exists and is valid
- [ ] Motivo is valid (01, 02, 03, or 04)

### After Encoding:
- [ ] CER base64 is ~1,948 chars
- [ ] KEY base64 is ~1,732 chars
- [ ] No whitespace in base64 strings
- [ ] Valid base64 character set (A-Z, a-z, 0-9, +, /, =)
- [ ] Proper padding (length % 4 == 0)

### API Request:
- [ ] Using correct endpoint: `/api/cancelacioncfdi/cancelarcsdv2`
- [ ] Token obtained from authentication
- [ ] Headers are SEPARATE (not concatenated)
- [ ] csdcer header contains only certificate base64
- [ ] csdkey header contains only key base64
- [ ] POST method with empty body
- [ ] Timeout set to 60 seconds

### API Response:
- [ ] HTTP code logged
- [ ] Response body logged
- [ ] Success/error message shown to user
- [ ] Database updated if successful
- [ ] Activity logged

---

## 🔧 Debugging Tips

### If Base64 Lengths Are Wrong:
```
Expected: 1948 and 1732
Actual: 3680 combined
```
**Problem:** Files are being concatenated
**Solution:** Already fixed - headers are separate

### If Format Detection Fails:
```
Error: CER detected as PEM format
```
**Problem:** Using PEM files instead of DER
**Solution:** Hardcoded paths point to DER files

### If Double Encoding Occurs:
```
CER base64 length: 3000+ chars
```
**Problem:** Encoding PEM base64 again
**Solution:** Check if PEM detection is working (line 508)

### If Authentication Fails:
```
Authentication failed: Invalid credentials
```
**Problem:** Wrong username/password
**Solution:** Check hardcoded values in `Fe_sat_digibox_client.php` (lines 28-29)

---

## 📝 After Testing

### If Successful:
1. ✅ Document the working configuration
2. ✅ Create settings UI for CSD upload
3. ✅ Remove hardcoded values
4. ✅ Test with production endpoint

### If Failed with Code 310 (CSD Inválido):
1. Check error logs for exact base64 lengths
2. Verify headers are separate (not concatenated)
3. Confirm DER format (not PEM)
4. Test with different certificate files
5. Contact DigiBox support with API logs

### If Failed with Code 304 (Certificate Expired):
1. Check certificate validity dates
2. Obtain new CSD from SAT portal
3. Update file paths to new certificate

---

## 🎯 Success Criteria

✅ **CER base64 length:** ~1948 chars
✅ **KEY base64 length:** ~1732 chars
✅ **Headers are separate:** `csdcer` and `csdkey` are distinct
✅ **DER format detected:** Binary files encoded correctly
✅ **HTTP 201 response:** UUID received for cancellation
✅ **Database updated:** `cancellation_status = 'pending'`
✅ **No encoding errors:** Valid base64 with proper padding

---

## 📞 Support

If testing reveals issues:

1. **Check logs first:** Activity log + error log
2. **Review API response:** Full HTTP response body
3. **Compare with test scripts:** Run `test_csd_formats.php` and `test_header_separation.php`
4. **Contact DigiBox:** Provide API request/response logs

**DigiBox Support:**
- Phone: (33) 3854.3949 or 01.800.1344.269
- Email: soporte@digibox.com.mx
