<?php
/**
 * PHPIMS
 *
 * Copyright (c) 2011 Christer Edvartsen <cogo@starzinger.net>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * * The above copyright notice and this permission notice shall be included in
 *   all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package PHPIMS
 * @subpackage Operations
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011, Christer Edvartsen
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/christeredvartsen/phpims
 */

namespace PHPIMS;

use PHPIMS\Database\Driver as DatabaseDriver;
use PHPIMS\Storage\Driver as StorageDriver;
use PHPIMS\Server\Response;
use PHPIMS\Operation\Exception as OperationException;

/**
 * Abstract operation class
 *
 * @package PHPIMS
 * @subpackage Operations
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011, Christer Edvartsen
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/christeredvartsen/phpims
 */
abstract class Operation implements OperationInterface {
    /**
     * The current hash value
     *
     * @param string
     */
    protected $hash = null;

    /**
     * HTTP method
     *
     * @var string
     */
    protected $method = null;

    /**
     * The database driver
     *
     * @var PHPIMS\Database\Driver
     */
    protected $database = null;

    /**
     * The storage driver
     *
     * @var PHPIMS\Storage\Driver
     */
    protected $storage = null;

    /**
     * Image instance
     *
     * The image object is populated with en empty instance of PHPIMS\Image when the operation
     * initializes.
     *
     * @var PHPIMS\Image
     */
    protected $image = null;

    /**
     * Response instance
     *
     * The response object is populated with en empty instance of PHPIMS\Server\Response when the
     * operation initializes.
     *
     * @var PHPIMS\Image
     */
    protected $response = null;

    /**
     * Array of class names to plugins to execute. The array has two elements, 'preExec' and
     * 'postExec' which both are numerically indexed.
     *
     * @var array
     */
    protected $plugins = array();

    /**
     * Configuration passed from the front controller
     *
     * @var array
     */
    protected $config = array();

    /**
     * Initialize the database driver
     *
     * @param array $config Part of the confguration array passed from the front controller
     */
    protected function initDatabaseDriver(array $config) {
        $params = array();

        if (isset($config['params'])) {
            $params = $config['params'];
        }

        $driver = new $config['driver']($params);

        $this->setDatabase($driver);
    }

    /**
     * Initialize the storage driver
     *
     * @param array $config Part of the confguration array passed from the front controller
     */
    protected function initStorageDriver(array $config) {
        $params = array();

        if (isset($config['params'])) {
            $params = $config['params'];
        }

        $driver = new $config['driver']($params);

        $this->setStorage($driver);
    }

    /**
     * Initialize plugins for this operation
     *
     * @param array $config Part of the confguration array passed from the front controller
     */
    protected function initPlugins(array $config) {
        // Operation name
        $operationName = $this->getOperationName();

        // Loop through the plugin paths, and see if there are any plugins that wants to execute
        // before and/or after this operation. Also sort them based in the priorities set in each
        // plugin class.
        $pluginPaths = array(
            dirname(__DIR__) => 'PHPIMS\\Operation\\Plugin\\',
        );

        // Append plugin paths from configuration
        foreach ($config as $spec) {
            $pluginPaths[$spec['path']] = isset($spec['prefix']) ? $spec['prefix'] : '';
        }

        // Initialize array for the plugins that will be executed
        $plugins = array(
            'preExec'  => array(),
            'postExec' => array()
        );

        foreach ($pluginPaths as $path => $prefix) {
            $path = rtrim($path, '/̈́') . '/';

            if (!empty($prefix)) {
                $path .= str_replace('\\', '/', $prefix);
            }

            if (empty($path) || !is_dir($path)) {
                continue;
            }

            $iterator = new \GlobIterator($path . '*Plugin.php');

            foreach ($iterator as $file) {
                $className = $prefix . $file->getBasename('.php');

                if (is_subclass_of($className, 'PHPIMS\\Operation\\Plugin')) {
                    $events = $className::$events;

                    $key = $operationName . 'PreExec';

                    if (isset($events[$key])) {
                        $priority = (int) $events[$key];
                        $plugin = new $className();
                        $plugins['preExec'][$priority] = $plugin;
                    }

                    $key = $operationName . 'PostExec';

                    if (isset($events[$key])) {
                        $priority = (int) $events[$key];
                        $plugin = new $className();
                        $plugins['postExec'][$priority] = $plugin;
                    }
                }
            }
        }

        // Sort to get the correct order
        ksort($plugins['preExec']);
        ksort($plugins['postExec']);

        $this->setPlugins($plugins);
    }

    /**
     * Get plugins array
     *
     * @return array
     */
    protected function getPlugins() {
        return $this->plugins;
    }

    /**
     * Set plugins array
     *
     * @param array $plugins Associative array with two keys: 'preExec' and 'postExec' which both
     *                       should be sorted numerically indexed arrays.
     */
    protected function setPlugins(array $plugins) {
        $this->plugins = $plugins;
    }

    /**
     * Init method
     *
     * @param array $config Configuration passed on from the front controller
     * @return PHPIMS\Operation
     * @codeCoverageIgnore
     */
    public function init(array $config) {
        $this->setConfig($config);

        $this->initDatabaseDriver($config['database']);
        $this->initStorageDriver($config['storage']);
        $this->initPlugins($config['plugins']);

        return $this;
    }

    /**
     * Get the current operation name
     *
     * @return string
     */
    protected function getOperationName() {
        $className = get_class($this);

        $operationName = substr($className, strrpos($className, '\\') + 1);
        $operationName = lcfirst($operationName);

        return $operationName;
    }

    /**
     * Get the current hash
     *
     * @return string
     */
    public function getHash() {
        return $this->hash;
    }

    /**
     * Set the hash property
     *
     * @param string $hash The hash to set
     * @return PHPIMS\Operation
     */
    public function setHash($hash) {
        $this->hash = $hash;

        return $this;
    }

    /**
     * Get the method
     *
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * Set the method
     *
     * @param string $method The method to set
     * @return PHPIMS\Operation
     */
    public function setMethod($method) {
        $this->method = $method;

        return $this;
    }

    /**
     * Get the database driver
     *
     * @return PHPIMS\Database\Driver
     */
    public function getDatabase() {
        return $this->database;
    }

    /**
     * Set the database driver
     *
     * @param PHPIMS\Database\Driver $driver The driver instance
     * @return PHPIMS\Operation
     */
    public function setDatabase(DatabaseDriver $driver) {
        $this->database = $driver;

        return $this;
    }

    /**
     * Get the storage driver
     *
     * @return PHPIMS\Storage\Driver
     */
    public function getStorage() {
        return $this->storage;
    }

    /**
     * Set the storage driver
     *
     * @param PHPIMS\Storage\Driver $driver The driver instance
     * @return PHPIMS\Operation
     */
    public function setStorage(StorageDriver $driver) {
        $this->storage = $driver;

        return $this;
    }

    /**
     * Get the current image
     *
     * @return PHPIMS\Image
     */
    public function getImage() {
        return $this->image;
    }

    /**
     * Set the image
     *
     * @param PHPIMS\Image $image The image object to set
     * @return PHPIMS\Operation
     */
    public function setImage(Image $image) {
        $this->image = $image;

        return $this;
    }

    /**
     * Get the response object
     *
     * @return PHPIMS\Server\Response
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * Set the response instance
     *
     * @param PHPIMS\Server\Response $response A response object
     * @return PHPIMS\Operation
     */
    public function setResponse(Response $response) {
        $this->response = $response;

        return $this;
    }

    /**
     * Get the configuration array
     *
     * @param string $key Optional key. If not specified the whole array will be returned
     * @return array
     */
    public function getConfig($key = null) {
        if ($key !== null) {
            return $this->config[$key];
        }

        return $this->config;
    }

    /**
     * Set the configuration array
     *
     * @param array $config Configuration array
     * @return PHPIMS\Operation
     */
    public function setConfig(array $config) {
        $this->config = $config;

        return $this;
    }

    /**
     * Trigger for registered "preExec" plugins
     *
     * @return PHPIMS\Operation
     * @throws PHPIMS\Operation\Plugin\Exception
     */
    public function preExec() {
        foreach ($this->plugins['preExec'] as $plugin) {
            $plugin->exec($this);
        }

        return $this;
    }

    /**
     * Trigger for registered "postExec" plugins
     *
     * @return PHPIMS\Operation
     * @throws PHPIMS\Operation\Plugin\Exception
     */
    public function postExec() {
        foreach ($this->plugins['postExec'] as $plugin) {
            $plugin->exec($this);
        }

        return $this;
    }

    /**
     * Factory method
     *
     * @param string $className The name of the operation class to instantiate
     * @param string $method The HTTP method used
     * @param string $hash Hash that will be passed to the operations constructor
     * @return PHPIMS\Operation
     * @throws PHPIMS\Operation\Exception
     */
    static public function factory($className, $method, $hash) {
        switch ($className) {
            case 'PHPIMS\\Operation\\AddImage':
            case 'PHPIMS\\Operation\\DeleteImage':
            case 'PHPIMS\\Operation\\EditMetadata':
            case 'PHPIMS\\Operation\\GetImage':
            case 'PHPIMS\\Operation\\GetMetadata':
            case 'PHPIMS\\Operation\\DeleteMetadata':
                $operation = new $className();

                $operation->setHash($hash);
                $operation->setMethod($method);
                $operation->setImage(new Image());
                $operation->setResponse(new Response());

                return $operation;
            default:
                throw new OperationException('Invalid operation', 500);
        }
    }
}