<div class="wrap">
	<h1 class="wp-heading-inline">Cavalcade Admin</h1>
	<?php
	$default_tab = null;
	$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;
	?>
	<nav class="nav-tab-wrapper">
		<a href="?page=cavalcade" class="nav-tab <?php if ($tab === null): ?>nav-tab-active<?php endif; ?>">Jobs</a>
		<a href="?page=cavalcade&tab=logs"
			 class="nav-tab <?php if ($tab === 'logs'): ?>nav-tab-active<?php endif; ?>">Logs</a>
	</nav>

	<form style="float: right;">
		<input type="hidden" name="page" value="cavalcade"/>
		<input type="hidden" name="tab" value="logs"/>
		<b>Filter:</b>
		<input type="text" name="filter" value="<?php echo $filter; ?>"/>
		<input type="submit" value="go"/>
	</form>

	<div class="tab-content">
		<?php if (count($logs) > 0) : ?>
			<p style="text-align: center;">
				<b>Total Logs:</b> <?php echo number_format($totalLogs); ?>
				<b>Showing:</b> <?php echo $offset + 1; ?> -
				<?php echo $offset + count($logs); ?>
			</p>
		<?php endif; ?>

		<table class="widefat striped">
			<tr>
				<th>Id</th>
				<th>Job id</th>
				<th>Status</th>
				<th>Timestamp</th>
				<th>Content</th>
				<th>Hook</th>
				<th>Args</th>
			</tr>
			<?php if (count($logs) > 0) :
				foreach ($logs as $log) :
					?>
					<tr>
						<td><?php echo $log->id; ?></td>
						<td><?php echo $log->job; ?></td>
						<td><?php echo $log->status; ?></td>
						<td><?php echo $log->timestamp; ?></td>
						<td><?php echo $log->content; ?></td>
						<td><?php echo $log->hook; ?></td>
						<td><?php echo $log->args; ?></td>
					</tr>
				<?php endforeach;
			else : ?>
				<tr>
					<td>None</td>
					<td></td>
				</tr>
			<?php endif; ?>
		</table>
		<?php if (count($logs) > 0) :
			$pagination = [];
			for ($i = 1; $i < $totalPages + 1 && $i <= 100; $i++) {
				if ($i === $currentPage) {
					$pagination[] = sprintf('<b style="font-size: larger;">%d</b>', $currentPage);
				} else {
					$pagination[] = sprintf('<a href="?page=cavalcade&amp;tab=logs&amp;paged=%d&amp;filter=%s">%d</a>', $i, urlencode($filter), $i);
				}
			}

			printf('<p style="line-height: 1.75em; text-align: center;"><b>Pages</b>: %s</p>', implode(' | ', $pagination));
		endif; ?>
	</div>
</div>
