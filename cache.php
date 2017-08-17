<?php
    define('ENABLE_APC', extension_loaded('apcu') || extension_loaded('apc'));
    define('ENABLE_OPCACHE', extension_loaded('Zend OPcache'));
    define('ENABLE_REALPATH', function_exists('realpath_cache_size'));

    if (ENABLE_APC) {
        if (!extension_loaded('apcu')) {
            function apcu_cache_info($limited = false) { return apc_cache_info('user', $limited); }
            function apcu_sma_info($limited = false) { return apc_sma_info($limited); }
            function apcu_fetch($key, &$success = null) { return apc_fetch($key, $success); }
            function apcu_delete($key) { return apc_delete($key); }
            class ApcuIterator extends ApcIterator {
                function __construct($search = null) { parent::__construct('user', $search); }
            }
        }

        $apcVersion = extension_loaded('apcu') ? 'APCu' : 'APC';
        $apc = array(
            'cache' => apcu_cache_info(),
            'sma' => apcu_sma_info(true)
        );
    }

    if (ENABLE_OPCACHE) {
        $opcache = opcache_get_status(true);
    }

    if (ENABLE_REALPATH) {
        $realpath = array();
        foreach( realpath_cache_get() as $path => $item ) {
            $realpath[] = array_merge(array('path' => $path), $item);
        }
        $realpathCacheUsed = realpath_cache_size();
        $realpathCacheTotal = machine_size(ini_get('realpath_cache_size'));
    }

    function is_action($action) {
        return isset( $_GET['action'] ) && $_GET['action'] == $action;
    }

	function percentage( $a, $b ) {
		return ( $a / $b ) * 100;
	}

	function has_key( $arr, $key1=null, $key2=null, $key3=null ) {
		if( isset( $arr[$key1] ) )
			return $key1;
		if( isset( $arr[$key2] ) )
			return $key2;
		if( isset( $arr[$key3] ) )
			return $key3;

		return null;
	}

	function get_key( $arr, $key1=null, $key2=null, $key3=null ) {
		$key = has_key($arr, $key1, $key2, $key3 );
		if( empty( $key ) )
			return null;

		return $arr[$key];
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

	function apcu_mem( $key ) {
		global $apc;

		if( $key == 'total' )
			return $apc['sma']['seg_size'];

		if( $key == 'free' )
			return $apc['sma']['avail_mem'];

		if( $key == 'used' )
			return apcu_mem('total') - apcu_mem('free');

		return 0;

	}

	function apcu_ref() {
		global $apc;

		if( !empty( $apc['cache']['cache_list'] ) )
			return current($apc['cache']['cache_list']);

		return array();
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

	function machine_size( $val ) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);

        if (!is_numeric($last)){
            $val = (int) substr($val, 0, -1);
        }

        if ($last == 'g')
            $val *= (1024 * 1024 * 1024);

        if ($last == 'm')
            $val *= (1024 * 1024);

        if ($last == 'k')
            $val *= 1024;

        return $val;
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

    /********************************/
    /*            OPcache           */
    /********************************/
    if (ENABLE_OPCACHE) {
        if( is_action('op_restart') ) {
            opcache_reset();
            redirect('?');
        }

        if( is_action('op_delete') ) {
            $selector = get_selector();

            foreach( $opcache['scripts'] as $key => $value ) {
                if( !preg_match( $selector, $key) ) continue;

                opcache_invalidate( $key, empty($_GET['force'])?false:true );
            }
            redirect('?action=op_select&selector=' . $_GET['selector'] );
        }
    }

    /********************************/
    /*              APC             */
    /********************************/
    if (ENABLE_APC) {
        if( is_action('apcu_restart') ) {
            apcu_delete( new ApcuIterator('#.*#') );
            redirect('?');
        }

        if( is_action('apcu_delete') ) {
            apcu_delete( new ApcuIterator(get_selector()) );
            redirect( '?action=apcu_select&selector=' . $_GET['selector'] );
        }
    }

    /********************************/
    /*           realpath           */
    /********************************/
    if (ENABLE_REALPATH) {
        if( is_action('realpath_clear') ) {
            clearstatcache(true);
            redirect('?action=realpath_show#realpath');
        }

        if( is_action('realpath_delete') ) {
            $selector = get_selector();

            foreach( $realpath as $item ) {
                if( !preg_match( $selector, $item['path']) ) continue;

                clearstatcache(true, $item['path']);
            }
            redirect('?action=realpath_show&selector=' . $_GET['selector'] . '#realpath');
        }
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
				Goto:
                <?=implode(" or ", array_filter(array(
                    ENABLE_OPCACHE ? '<a href="#opcache">PHP Opcache</a>' : null,
                    ENABLE_APC ? '<a href="#apcu">' . $apcVersion . '</a>' : null,
                    ENABLE_REALPATH ? '<a href="#realpath">Realpath</a>' : null
                ))) ?>
			</div>

            <?php if (ENABLE_OPCACHE): ?>
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
                <?php if( is_action('op_select') ): ?>
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
            <?php endif; ?>

            <?php if(ENABLE_APC): ?>
                <h2 id="apcu"><?=$apcVersion?></h2>
                <div>
                    <h3>Memory <?=human_size(apcu_mem('used'))?> of <?=human_size(apcu_mem('total'))?></h3>
                    <div class="full bar green">
                        <div class="orange" style="width: <?=percentage(apcu_mem('used'), apcu_mem('total'))?>%"></div>
                    </div>
                </div>
                <div>
                    <h3>Actions</h3>
                    <form action="?" method="GET">
                        <label>Cache:
                            <button name="action" value="apcu_restart">Restart</button>
                        </label>
                    </form>
                    <form action="?" method="GET">
                        <label>Key(s):
                            <input name="selector" type="text" value="" placeholder=".*" />
                        </label>
                        <button type="submit" name="action" value="apcu_select">Select</button>
                        <button type="submit" name="action" value="apcu_delete">Delete</button>
                        <label><input type="checkbox" name="apcu_show_expired" <?=isset($_GET['apcu_show_expired'])?'checked="checked"':''?> />Show expired</label>
                    </form>
                </div>
                <?php if( is_action('apcu_view') ): ?>
                <div>
                    <h3>Value for <?=htmlentities('"'.$_GET['selector'].'"')?></h3>
                    <pre><?=htmlentities(var_export(apcu_fetch(urldecode($_GET['selector'])), true)); ?></pre>
                </div>
                <?php endif; ?>
                <?php if( is_action('apcu_select') ): ?>
                <div>
                    <h3>Keys matching <?=htmlentities('"'.$_GET['selector'].'"')?></h3>
                    <table>
                        <thead>
                            <tr>
                                <th><a href="<?=sort_url(has_key(apcu_ref(), 'key', 'info'))?>">Key</a></th>
                                <th><a href="<?=sort_url(has_key(apcu_ref(), 'nhits', 'num_hits'))?>">Hits</a></th>
                                <th><a href="<?=sort_url('mem_size')?>">Size</a></th>
                                <th><a href="<?=sort_url('ttl')?>">TTL</a></th>
                                <th>Expires</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tfoot></tfoot>

                        <tbody>
                        <?php foreach( sort_list($apc['cache']['cache_list']) as $item ):
                            $expired = !isset( $_GET['apcu_show_expired'] ) && $item['ttl'] > 0 && get_key($item, 'mtime', 'modification_time') + $item['ttl'] < time();
                            if( !preg_match(get_selector(), get_key($item, 'key', 'info')) || $expired ) continue;?>
                            <tr>
                                <td><?=get_key($item, 'key', 'info')?></td>
                                <td><?=get_key($item, 'nhits', 'num_hits')?></td>
                                <td><?=human_size($item['mem_size'])?></td>
                                <td><?=$item['ttl']?></td>
                                <td><?=($item['ttl'] == 0 ? 'indefinite' : date('Y-m-d H:i', get_key($item, 'mtime', 'modification_time') + $item['ttl'] ))?></td>
                                <td>
                                    <a href="?action=apcu_delete&selector=<?=urlencode('^'.get_key($item, 'key', 'info').'$')?>">Delete</a>
                                    <a href="?action=apcu_view&selector=<?=urlencode(get_key($item, 'key', 'info'))?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if(ENABLE_REALPATH): ?>
                <h2 id="realpath">Realpath</h2>
                <div>
                    <h3>Memory <?=human_size($realpathCacheUsed)?> of <?=human_size($realpathCacheTotal)?></h3>
                    <div class="full bar green">
                        <div class="orange" style="width: <?=percentage($realpathCacheUsed, $realpathCacheTotal)?>%"></div>
                    </div>
                    <div>
                        <h3>Actions</h3>
                        <form action="?" method="GET">
                            <label>Cache:
                                <button name="action" value="realpath_clear">Restart</button>
                            </label>
                        </form>
                        <form action="?" method="GET">
                            <label>Key(s):
                                <input name="selector" type="text" value="" placeholder=".*" />
                            </label>
                            <button type="submit" name="action" value="realpath_select">Select</button>
                            <button type="submit" name="action" value="realpath_delete">Delete</button>
                        </form>
                    </div>

                    <?php if( is_action('realpath_select') ): ?>
                        <div>
                            <table>
                                <thead>
                                <tr>
                                    <th><a href="<?=sort_url('path')?>">Path</a></th>
                                    <th><a href="<?=sort_url('is_dir')?>">Is Directory</a></th>
                                    <th><a href="<?=sort_url('realpath')?>">Realpath</a></th>
                                    <th><a href="<?=sort_url('expires')?>">Expires</a></th>
                                    <th><a href="<?=sort_url('key')?>">Key</a></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach( sort_list($realpath) as $item ):
                                    if( !preg_match(get_selector(), $item['path']) ) continue;?>
                                    <tr>
                                        <td><?=$item['path']?></td>
                                        <td><?=$item['is_dir'] ? '&#10004;' : ''?></td>
                                        <td><?=$item['realpath']?></td>
                                        <td><?=date('Y-m-d H:i:s', $item['expires'])?></td>
                                        <td><?=sprintf('%u', $item['key'])?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
		</div>
	</body>
</html>
