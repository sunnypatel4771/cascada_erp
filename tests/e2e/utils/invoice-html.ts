/**
 * Extract Perfex invoice edit customer id from raw HTML (select#clientid).
 */
export function parseInvoiceClientIdFromHtml(html: string): string | null {
  const block = html.match(/<select[^>]*\bid="clientid"\b[^>]*>([\s\S]*?)<\/select>/i);
  if (!block) {
    return null;
  }
  const inner = block[1];
  const selected =
    inner.match(/<option[^>]*\bvalue="(\d+)"[^>]*\bselected\b/i) ||
    inner.match(/<option[^>]*\bselected\b[^>]*\bvalue="(\d+)"\b/i);
  return selected ? selected[1] : null;
}
