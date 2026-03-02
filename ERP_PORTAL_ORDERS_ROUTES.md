# ERP Portal Orders (http://erp.local/) vs Routes: Analysis

## Quick Answer
**NO** - Orders created directly in the ERP system at `http://erp.local/` are **NOT** being considered for route generation.

---

## Evidence

### 1. How ERP Portal Orders are Created
**Location**: `application/controllers/Clients.php::save_new_order()` (Line 152)

When a customer creates an order on the main ERP portal (home.php):

```php
public function save_new_order()
{
    // Creates an INVOICE, not an omni_sales order
    $invoice_data = [
        'clientid' => $client_id,
        'number' => get_option('next_invoice_number'),
        'date' => date('Y-m-d'),
        'duedate' => date('Y-m-d', strtotime('+30 days')),
        // ... more invoice fields
    ];
    
    // Insert into tblinvoices table
    $invoice_id = $this->invoices_model->add($invoice_data);
    
    // Items are stored in tblitemable (rel_type = 'invoice')
    $this->db->where('rel_id', $invoice_id)
             ->where('rel_type', 'invoice')
             ->get(db_prefix() . 'itemable');
}
```

**Key Point**: ERP orders are created as **invoices** in `tblinvoices`, NOT as orders in `tblcart`

---

### 2. Route Generation Only Queries tblcart
**Location**: `modules/ramos/models/Routes_model.php::generate_routes()` (Line 167)

```php
$this->db->from(db_prefix() . 'cart c');  // ONLY tblcart
$this->db->where('c.channel_id IN (1,2,4,6)', null, false);
```

**Critical Issue**: Routes only pull from `tblcart` table, not from `tblinvoices`

---

## Comparison: Two Separate Order Systems

### ERP Portal Orders (http://erp.local/)
```
Customer logs in at http://erp.local/home.php
         ↓
Creates order using "New Order" form
         ↓
Order stored in tblinvoices table
         ↓
Items stored in tblitemable (rel_type='invoice')
         ↓
NO channel_id assignment
         ↓
Routes generation query searches tblcart (NOT tblinvoices)
         ↓
ERP portal orders NOT included in routes ❌
```

### Omni_sales Portal Orders
```
Customer logs in at /omni_sales/omni_sales_client/
         ↓
Creates order using omni_sales portal
         ↓
Order stored in tblcart table
         ↓
Items stored in tblcart_detailt
         ↓
channel_id = 2 assigned
         ↓
Routes generation query searches tblcart WITH channel_id IN (1,2,4,6)
         ↓
Omni_sales portal orders ARE included in routes ✅
```

---

## The Code Difference

### ERP Portal - Uses invoices_model->add()
```php
// application/controllers/Clients.php::save_new_order()
$invoice_id = $this->invoices_model->add($invoice_data);  // Saves to tblinvoices
```

**Result**: Data in `tblinvoices` and `tblitemable`

---

### Omni_sales Portal - Uses omni_sales_model->check_out()
```php
// modules/omni_sales/models/Omni_sales_model.php::check_out()
$channel_id = 2;
$this->db->insert(db_prefix() . 'cart', $data_cart);  // Saves to tblcart
```

**Result**: Data in `tblcart` and `tblcart_detailt`

---

## Route Generation Query

```php
// modules/ramos/models/Routes_model.php::generate_routes()

$this->db->select('c.id, c.order_number, ...');
$this->db->from(db_prefix() . 'cart c');  // ← ONLY queries tblcart

$this->db->where('c.status !=', 5);
$this->db->where('c.channel_id IN (1,2,4,6)', null, false);  // ← Looks for channel_id
$this->db->where('c.original_order_id IS NULL', null, false);
// ... more filters ...

$orders = $this->db->get()->result_array();
```

**What This Query Includes**:
- ✅ Omni_sales portal orders (tblcart with channel_id 1,2,4,6)
- ❌ ERP portal orders (tblinvoices - not even queried)

---

## Why This Separation Exists

The two systems are independent:

1. **ERP Core System** (invoices, projects, payments)
   - Uses `tblinvoices` for invoicing/orders
   - Legacy system, integrated with CRM features
   - Client management in `tblclients`

2. **Omni_sales Module** (modern e-commerce)
   - Uses `tblcart` for orders
   - Multi-channel support (portal, POS, etc.)
   - Warehouse integration
   - Modern order management

**Routes Module** was integrated with omni_sales (tblcart) only, not with the core ERP invoicing system.

---

## Summary Answer

**To directly answer your question:**

**❌ NO - Orders created at http://erp.local/ are NOT considered for routes.**

### Why?

1. **Different Tables**: 
   - ERP orders → `tblinvoices` + `tblitemable`
   - Omni_sales orders → `tblcart` + `tblcart_detailt`

2. **Route Query Limitation**: 
   - Routes only query `FROM tblcart`
   - Never queries `tblinvoices`

3. **Missing channel_id**: 
   - ERP orders don't get a `channel_id` field
   - Routes filter by `channel_id IN (1,2,4,6)`
   - ERP orders can't match this filter

### Implications

- ERP portal orders are treated as regular invoices only
- They do NOT flow into the delivery/routing workflow
- Only omni_sales orders (from the separate commerce portal) get routed for delivery
- Two completely separate order systems running in parallel

### To Fix This (If Needed)

Would require:
1. Extracting omni_sales orders from omni_sales portal, OR
2. Modifying route generation to also query `tblinvoices`, OR
3. Creating a sync mechanism between ERP invoices and ramos routes
