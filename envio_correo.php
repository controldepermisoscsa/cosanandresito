<?php
// Stub de compatibilidad para funciones legacy (no hace envíos reales).
// Si ya existe en tu proyecto una versión con lógica, ignora este archivo.

if (!function_exists('enviarCorreoConAdjunto')) {
    require_once __DIR__ . '/config_correo.php';

    function enviarCorreoConAdjunto($id_permiso, $rutaArchivo) {
        error_log("⚠️ DEPRECATED: enviarCorreoConAdjunto() - usar EstadoCorreoManager en su lugar");
        if (!file_exists($rutaArchivo)) {
            throw new Exception("Archivo PDF no encontrado: {$rutaArchivo}");
        }
        // Respuesta de compatibilidad mínima
        return [
            'success' => true,
            'destinatarios' => [],
            'archivo' => basename($rutaArchivo),
            'modo' => 'legacy_stub'
        ];
    }
}
