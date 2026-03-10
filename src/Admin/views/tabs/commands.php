<?php
/**
 * Commands tab template — Routes to list or form view.
 *
 * Variables available from $data:
 *   $data['view']     string  Current view: 'list', 'new', or 'edit'.
 *   $data['commands'] array   Commands with source metadata (list view).
 *   $data['slug']     string  Command slug (edit view).
 *   $data['command']  array   Command row data (edit view).
 *   $data['models']   array   Available models (form views).
 *
 * @since 1.2.0
 *
 * @package WordPress\InfomaniakAiToolkit
 */

defined('ABSPATH') || exit;

$view = $data['view'] ?? 'list';

if ($view === 'new' || $view === 'edit') {
	include __DIR__ . '/commands/form.php';
} else {
	include __DIR__ . '/commands/list.php';
}
