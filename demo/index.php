<?php
    /**
     * DEMO PAGE
     *
     * This page is only use to load the demo on Heroku!
     * This page requires PHP >= 7
     *
     * Please use the `cache.php` file directly for your own deployments.
     */
    class CacheDemoClass {
        public $hello = 'world';
    }

    define('INIT', !preg_match('#herokuapp.com|localhost|127.0.0.1#', $_SERVER['HTTP_REFERER'] ?? ''));

    if (INIT) {
        apcu_store('type.bool.true', true);
        apcu_store('type.bool.false', true);
        apcu_store('type.numeric.10', 10);
        apcu_store('type.numeric.10000', 10000);
        apcu_store('type.numeric.666666', 666666);
        apcu_store('type.string', 'Hello World');
        apcu_store('type.string.expires', 'Hello World', 3600 * 12);
        apcu_store('type.array', array('hello', 'world'));
        apcu_store('type.array', array('hello' => 'world'));
        apcu_store('type.array.assoc', array('hello' => 'world'), 3600 * 24);
        apcu_store('type.object', (object)array('hello' => 'world'), 3600 * 24);
        apcu_store('type.class-instance', new CacheDemoClass(), 3600);
    }

    if (INIT) {
        // Memcache configuration
        $memcache_host = getenv('MEMCACHE_HOST') ?: '127.0.0.1';
        $memcache_port = getenv('MEMCACHE_PORT') ?: 11211;
        $memcache_user = getenv('MEMCACHE_USER') ?: null;
        $memcache_password = getenv('MEMCACHE_PASSWORD') ?: null;

        $memcache = new \Memcached();
        $memcache->addServer($memcache_host, $memcache_port);
        if (!empty($memcache_user) && !empty($memcache_password)) {
            $memcache->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
            $memcache->setSaslAuthData($memcache_user, $memcache_password);
        }

        $memcache->add('type.array', ['abc', 'def']);
        $memcache->add('type.string', 'hello-world');
        $memcache->add('type.ttl.string', 'hello-world', time() + 3600);

        // Redis configuration
        $redis_host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $redis_port = getenv('REDIS_PORT') ?: 6379;
        $redis_password = getenv('REDIS_PASSWORD') ?: null;
        $redis_database = getenv('REDIS_DATABASE') ?: null;

        $redis = new Redis();
        try {
            $redis->connect($redis_host, $redis_port);

            if (!empty($redis_password))
                $redis->auth($redis_password);

            if (!empty($redis_database))
                $redis->select($redis_database);

            $redis->sAdd('type.set', 'abc', 'def');
            $redis->set('type.string', 'hello-world');
            $redis->setEx('type.ttl.string', 3600, 'hello-world');
        } catch(Exception $ex) {}

    }

    require_once('../cache.php');
