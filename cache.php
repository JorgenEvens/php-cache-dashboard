<?php
	$opcache = opcache_get_status(true);
	$apc = array(
		'cache' => apc_cache_info('user'),
		'sma' => apc_sma_info(true)
	);

	function percentage( $a, $b ) {
		return ( $a / $b ) * 100;
	}

	function opcache_mem( $key ) {
		global $opcache;

		if( $key == 'total' )
			return opcache_mem('free') + opcache_mem('used') + opcache_mem('wasted');

		if( in_array( $key, array( 'used', 'free', 'wasted' ) ) )
			$key = $key . '_memory';

		return $opcache['memory_usage'][$key];
	}

	function opcache_stat( $stat ) {
		global $opcache;

		return $opcache['opcache_statistics'][$stat];
	}

	function apc_mem( $key ) {
		global $apc;

		if( $key == 'total' )
			return $apc['sma']['seg_size'];

		if( $key == 'free' )
			return $apc['sma']['avail_mem'];

		if( $key == 'used' )
			return apc_mem('total') - apc_mem('free');

		return 0;

	}

	function human_size( $s ) {
		$size = 'B';
		$sizes = array( 'KB', 'MB', 'GB' );

		while( $s > 1024 ) {
			$size = array_shift( $sizes );
			$s /= 1024;
		}

		$s = round( $s, 2 );
		return $s . ' ' . $size;
	}

	function redirect($url) {
		header('Status: 302 Moved Temporarily');
		header('Location: '. $url);
		exit();
	}

	function get_selector() {
		return '#' . str_replace( '#', '\#', urldecode($_GET['selector']) ) . '#';
	}

	function sort_url($on) {
		$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
		if( empty( $query ) )
			$query = '';
		else
			$query .= '&';

		$query = preg_replace( '#sort=[^&]+&?#', '', $query );
		$query = preg_replace( '#order=[^&]+&?#', '', $query );
		
		if( !isset( $_GET['order'] ) )
			$_GET['order'] = '';

		$query .= 'sort=' . urlencode($on);
		$query .= '&order=' . ( $_GET['order'] == 'asc' ? 'desc' : 'asc' );

		return '?' . $query;
	}

	function sort_list(&$list) {
		if( !isset( $_GET['sort'] ) )
			return $list;

		$key = urldecode($_GET['sort']);
		$reverse = isset($_GET['order']) ? ( urldecode($_GET['order']) == 'desc' ) : false;
		usort($list, function( $item1, $item2 ) use ( $key, $reverse ) {
			if( $reverse ) {
				$tmp = $item1;
				$item1 = $item2;
				$item2 = $tmp;
				unset($tmp);
			}
			if( is_string( $item1[$key] ) || is_string( $item2[$key] ) )
				return strcmp( $item1[$key], $item2[$key] );

			return $item1[$key] - $item2[$key];
		});

		return $list;
	}

	// Opcache

	if( isset( $_GET['action'] ) && $_GET['action'] == 'op_restart' ) {
		opcache_reset();
		redirect('?');
	}

	if( isset( $_GET['action'] ) && $_GET['action'] == 'op_delete' ) {
		$selector = get_selector();
		
		foreach( $opcache['scripts'] as $key => $value ) {
			if( !preg_match( $selector, $key) ) continue;

			opcache_invalidate( $key, empty($_GET['force'])?false:true );
		}
		redirect('?action=op_select&selector=' . $_GET['selector'] );
	}

	// APC
	if( isset( $_GET['action'] ) && $_GET['action'] == 'apc_restart' ) {
		apc_delete( new ApcIterator('#.*#') );
		redirect('?');
	}

	if( isset( $_GET['action'] ) && $_GET['action'] == 'apc_delete' ) {
		apc_delete( new ApcIterator('user',get_selector()) );
		redirect( '?action=apc_select&selector=' . $_GET['selector'] );
	}
?><html>
	<head>
		<title>Cache Status</title>
		<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1" />
		<style>
		html, body { font-family: Arial, sans-serif;}
		.wrap { max-width: 960px; margin: 0 auto;}
		.full { width: 100%; }
		.green { background: green; }
		.red { background: red; }
		.orange { background: orange; }
		.bar { height: 20px; overflow: hidden; border-radius: 4px 4px; }
		.bar div { height: 20px; float: left; }
		.bar, .bar div { background-image: repeating-linear-gradient(45deg, transparent 0, rgba( 255,255,255,0.3) 1px, rgba(255,255,255,0.3) 10px, transparent 11px, transparent 18px); background-repeat: repeat-x; }
		label { font-weight: bold; }
		table { border-spacing: 0; }
		table td { padding: 0.2em 1em; }
		table th { background: #686868; color: white; padding: 0.5em 1em 0.2em 1em; font-weight: normal; }
		table th a { text-decoration: none; color: white; cursor: pointer; }
		table tr:nth-child(2n+1) { background: #efefef;	}
		@media screen and (max-width: 480px) {
			input { width: 40%; }
		}
		</style>
	</head>

	<body>
		<div class="wrap">
			<div>
				Goto: <a href="#opcache">PHP Opcache</a> or <a href="#apcu">APCu</a>
			</div>
			<h2 id="opcache">PHP Opcache</h2>
			<div>
				<h3>Memory <?=human_size(opcache_mem('used')+opcache_mem('wasted'))?> of <?=human_size(opcache_mem('total'))?></h3>
				<div class="full bar green">
					<div class="orange" style="width: <?=percentage(opcache_mem('used'), opcache_mem('total'))?>%"></div>
					<div class="red" style="width: <?=percentage(opcache_mem('wasted'), opcache_mem('total'))?>%"></div>
				</div>
			</div>
			<div>
				<h3>Keys <?=opcache_stat('num_cached_keys')?> of <?=opcache_stat('max_cached_keys')?></h3>
				<div class="full bar green">
					<div class="orange" style="width: <?=percentage(opcache_stat('num_cached_keys'), opcache_stat('max_cached_keys'))?>%"></div>
				</div>
			</div>
			<div>
				<h3>Cache hit <?=round(opcache_stat('opcache_hit_rate'),2)?>%</h3>
				<div class="full bar green">
					<div class="red" style="width: <?=100-opcache_stat('opcache_hit_rate')?>%"></div>
				</div>
			</div>
			<div>
				<h3>Actions</h3>
				<form action="?" method="GET">
					<label>Cache:
						<button name="action" value="op_restart">Restart</button>
					</label>
				</form>
				<form action="?" method="GET">
					<label>Key(s):
						<input name="selector" type="text" value="" placeholder=".*" />
					</label>
					<button type="submit" name="action" value="op_select">Select</button>
					<button type="submit" name="action" value="op_delete">Delete</button>
					<label>
						<input name="force" type="checkbox" />
						Force deletion
					</label>
				</form>
			</div>
			<?php if( isset( $_GET['action'] ) && $_GET['action'] == 'op_select' ): ?>
			<div>
				<h3>Keys matching <?=htmlentities('"'.$_GET['selector'].'"')?></h3>
				<table>
					<thead>
						<tr>
							<th><a href="<?=sort_url('full_path')?>">Key</a></th>
							<th><a href="<?=sort_url('hits')?>">Hits</a></th>
							<th><a href="<?=sort_url('memory_consumption')?>">Size</a></th>
							<th>Action</th>
						</tr>
					</thead>

					<tfoot></tfoot>

					<tbody>
					<?php foreach( sort_list($opcache['scripts']) as $item ):
						if( !preg_match(get_selector(), $item['full_path']) ) continue;?>
						<tr>
							<td><?=$item['full_path']?></td>
							<td><?=$item['hits']?></td>
							<td><?=human_size($item['memory_consumption'])?></td>
							<td>
								<a href="?action=op_delete&selector=<?=urlencode('^'.preg_quote($item['full_path']).'$')?>">Delete</a>
								<a href="?action=op_delete&force=1&selector=<?=urlencode('^'.preg_quote($item['full_path']).'$')?>">Force Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<h2 id="apcu">APCu</h2>
			<div>
				<h3>Memory <?=human_size(apc_mem('used'))?> of <?=human_size(apc_mem('total'))?></h3>
				<div class="full bar green">
					<div class="orange" style="width: <?=percentage(apc_mem('used'), apc_mem('total'))?>%"></div>
				</div>
			</div>
			<div>
				<h3>Actions</h3>
				<form action="?" method="GET">
					<label>Cache:
						<button name="action" value="apc_restart">Restart</button>
					</label>
				</form>
				<form action="?" method="GET">
					<label>Key(s):
						<input name="selector" type="text" value="" placeholder=".*" />
					</label>
					<button type="submit" name="action" value="apc_select">Select</button>
					<button type="submit" name="action" value="apc_delete">Delete</button>
					<label><input type="checkbox" name="apc_show_expired" <?=isset($_GET['apc_show_expired'])?'checked="checked"':''?> />Show expired</label>
				</form>
			</div>
			<?php if( isset( $_GET['action'] ) && $_GET['action'] == 'apc_view' ): ?>
			<div>
				<h3>Value for <?=htmlentities('"'.$_GET['selector'].'"')?></h3>
				<pre><?php var_dump( apc_fetch(urldecode($_GET['selector'])) ); ?></pre>
			</div>
			<?php endif; ?>
			<?php if( isset( $_GET['action'] ) && $_GET['action'] == 'apc_select' ): ?>
			<div>
				<h3>Keys matching <?=htmlentities('"'.$_GET['selector'].'"')?></h3>
				<table>
					<thead>
						<tr>
							<th><a href="<?=sort_url('key')?>">Key</a></th>
							<th><a href="<?=sort_url('nhits')?>">Hits</a></th>
							<th><a href="<?=sort_url('mem_size')?>">Size</a></th>
							<th><a href="<?=sort_url('ttl')?>">TTL</a></th>
							<th>Expires</th>
							<th>Action</th>
						</tr>
					</thead>

					<tfoot></tfoot>

					<tbody>
					<?php foreach( sort_list($apc['cache']['cache_list']) as $item ):
						if( !preg_match(get_selector(), $item['key']) || ( !isset( $_GET['apc_show_expired'] ) && $item['mtime'] + $item['ttl'] < time() ) ) continue;?>
						<tr>
							<td><?=$item['key']?></td>
							<td><?=$item['nhits']?></td>
							<td><?=human_size($item['mem_size'])?></td>
							<td><?=$item['ttl']?></td>
							<td><?=date('Y-m-d H:i', $item['mtime'] + $item['ttl'] )?></td>
							<td>
								<a href="?action=apc_delete&selector=<?=urlencode('^'.$item['key'].'$')?>">Delete</a>
								<a href="?action=apc_view&selector=<?=urlencode($item['key'])?>">View</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
		</div>
	</body>
</html>