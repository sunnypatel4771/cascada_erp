# Production verification: invoice merge + Ramos invoice pricing

Use this checklist on the live Perfex **Ramos** deployment after releases that touch invoicing or Facturación.

## URLs

1. **Invoice list**  
   Open `…/admin/invoices/list_invoices` and confirm the list loads and permissions behave as expected for the staff member you use.

2. **Invoice edit (merge panel)**  
   Open `…/admin/invoices/invoice/{id}` (example: invoice `34` if that exists in production).  
   - If the invoice status allows merging (not paid, not partially paid, not cancelled), confirm the **“Invoices available for merging”** block appears when other eligible invoices exist for the **same customer**.  
   - Confirm every merge candidate still belongs to that same customer (spot-check invoice numbers / customers in a second tab).

3. **Ramos Facturación → invoice lines**  
   Open `…/admin/ramos/facturacion` (pick the delivery **date** that shows the route/order you care about). Generate or open the Perfex invoice linked from an order and confirm line **rates** match your business rules:  
   - Customer **`week` = 1** with a matching **`ramos_price_rules`** row: base from rule price minus rule discount, then apply customer markup **`customers_descuento`**.  
   - Otherwise: **`purchase_price`** from inventory × (1 + **`customers_descuento`** / 100).  
   Implementation reference: `modules/ramos/libraries/Ramos_invoice_generator.php` and `modules/ramos/models/Pricing_model.php`.  
   When **unit equivalences** (below) are implemented, totals should also be validated in the **order unit** shown to the customer (e.g. caja) against the **pricing base unit** (e.g. KG).

4. **SAT / CFDI (electronic fiscal invoice)**  
   - **Client expectation:** Staff may choose **one or several orders** to produce a **SAT** invoice; **every selected order must belong to the same customer**, because SAT documents tie to tax reporting for that taxpayer. **Not every** Perfex invoice needs to be stamped / converted to SAT—only those required for tax.  
   - **Current product (reference):** The **FE-SAT** module stamps **one Perfex invoice at a time** (e.g. `…/admin/fe_sat/form/{invoice_id}`). **Invoice merge** on the invoice screen already limits merge candidates to the **same customer** (`Invoices_model::check_for_merge_invoice`), which aligns with “same customer” when combining billing into one invoice before SAT. A dedicated **multi-order picker → one SAT flow** (if different from merge + timbrar) is a separate UX/backend feature to scope if the client needs it.  
   - **Ramos Facturación:** `Facturacion::generate_fesat()` is **per order** and depends on a linked Perfex invoice; provider integration is gated by options such as `ramos_fesat_enabled` (see `modules/ramos/controllers/Facturacion.php`).

## Headed Playwright (optional)

- Merge + same-customer checks: `tests/e2e/specs/22.invoice-merge-same-customer.spec.ts`  
  Configure `PW_MERGE_TEST_INVOICE_ID` (or `PW_INVOICE_ID`) to an invoice that has merge candidates.

- Week / rule / markup: `tests/e2e/specs/23.ramos-facturacion-invoice-week-pricing.spec.ts`  
  Requires explicit env for a **READY** Ramos order without an invoice yet, plus expected pricing inputs — see comments at the top of that spec.

## Equivalences (client definition — implementation still to scope)

The client has clarified that **equivalences** are primarily **unit conversions for pricing**, not arbitrary SKU substitution:

- Customers place orders in **commercial units** (example: **caja** / box).  
- **Pricing and weekly rules** are often maintained in a **base unit** (example: **KG**).  
- An **equivalence** converts the ordered quantity into that base unit so the **order total and later the invoice** use the **correct monetary amount** (convert → apply unit price from rules or cost path → line total).

**Code status today:** `Ramos_invoice_generator` uses order line quantity and inventory-backed pricing; there is **no** dedicated caja→KG (or generic order-unit→base-unit) layer in that path yet. Adding it implies: where factors live (per **inventory item**, per **customer**, global), rounding, and whether the **invoice line** should display order unit, base unit, or both.

**Residual questions for implementation (narrower than before)**

1. Is the conversion factor **one per inventory SKU** (e.g. 1 caja = X KG for that product only), or can it vary **per customer**?  
2. Should **Facturación** show amounts in the **order unit**, **base unit**, or both after conversion?  
3. If an order line has no configured equivalence, should generation **block**, **warn**, or **fall back** to current behavior?

**Examples to validate with the client after a first build**

- **Example A:** Rule price is **per KG**; customer orders **2 cajas**; equivalence says **1 caja = 5 KG** → priced quantity **10 KG** × rule rate (+ markup as today).  
- **Example B:** Same product, two customers with **different** caja weights → confirm whether factors are customer-specific.  
- **Example C:** Order unit is already **KG** (matches base) → **1:1**, no conversion.

Implementation will hook into order totals and `Ramos_invoice_generator` (and any UI that edits line prices on Facturación) once the data model for factors is agreed.
