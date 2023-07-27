<?php
/**
 * phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
 */

namespace HM\Cavalcade\Plugin\AdminUI;

use function HM\Cavalcade\Plugin\get_jobs;
use function HM\Cavalcade\Plugin\get_logs;
use function HM\Cavalcade\Plugin\get_logs_count;

const PAGE_NAME = 'cavalcade';

function bootstrap()
{
	add_management_page('Cavalcade Admin', 'Cavalcade Admin', 'administrator', PAGE_NAME, __NAMESPACE__ . '\\renderPage');
}

function renderPage()
{
	$tab = $_GET['tab'] ?? null;

	if ($tab === 'logs') {
		renderLogsTab();
		return;
	}

	renderJobsTab();
}

function renderLogsTab()
{
	$limit = 20;
	$currentPage = (int)($_GET['paged'] ?? 1);
	$offset = ($currentPage - 1) * $limit;
	$filter = $_GET['filter'] ?? '';

	$logs = get_logs($offset, $filter, $limit);
	$totalLogs = get_logs_count($filter);
	$totalPages = $totalLogs / $limit;

	include __DIR__ . '/views/logs.php';
}

function renderJobsTab()
{
	$jobs = get_jobs();
	include __DIR__ . '/views/jobs.php';
}

