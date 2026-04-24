<?php

defined('BASEPATH') or exit('No direct script access allowed');

$lang['importar_productos_menu'] = 'Importar productos';
$lang['importar_productos_title'] = 'Importar productos (Items)';
$lang['importar_productos_upload_help'] = 'Formatos: CSV, XLS, XLSX. La primera fila debe contener los encabezados de columnas.';
$lang['importar_productos_map_help'] = 'Asigna cada columna del archivo a un campo de artículo o a un campo personalizado (items). Usa «Ignorar» para columnas que no aplican.';
$lang['importar_productos_ignore'] = 'Ignorar';
$lang['importar_productos_db_field'] = 'Campo artículo';
$lang['importar_productos_cf_field'] = 'Campo personalizado';
$lang['importar_productos_save_mapping'] = 'Guardar mapeo';
$lang['importar_productos_simulate'] = 'Simular importación';
$lang['importar_productos_import'] = 'Importar';
$lang['importar_productos_cancel'] = 'Cancelar y empezar de nuevo';
$lang['importar_productos_duplicate_check'] = 'Bloquear duplicados dentro del archivo';
$lang['importar_productos_duplicate_check_db'] = 'Bloquear duplicados contra la base de datos';
$lang['importar_productos_duplicate_by'] = 'Detectar duplicados por';
$lang['importar_productos_dup_description'] = 'Descripción (description)';
$lang['importar_productos_dup_sku'] = 'SKU (sku_code)';
$lang['importar_productos_preview_headers'] = 'Encabezados detectados';
$lang['importar_productos_preview_rows'] = 'Vista previa (primeras filas)';
$lang['importar_productos_mapping'] = 'Mapeo de columnas';
$lang['importar_productos_required_note'] = 'Debes mapear al menos: description y rate.';
$lang['importar_productos_results_simulated'] = 'Simulación completada (no se escribió en la base de datos).';
$lang['importar_productos_results_imported'] = 'Importación completada.';
$lang['importar_productos_read_error'] = 'No se pudo leer el archivo Excel. Verifica el formato o exporta a CSV UTF-8.';
$lang['importar_productos_session_invalid'] = 'Sesión inválida. Sube el archivo de nuevo.';
$lang['importar_productos_ramos_sync_group'] = 'Ramos (inventario)';
$lang['importar_productos_ramos_has_maduracion'] = 'Maduración (has_maduracion en ramos_inventory_items)';
