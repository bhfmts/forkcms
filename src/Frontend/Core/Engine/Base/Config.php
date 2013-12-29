<?php

namespace Frontend\Core\Engine\Base;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * This is the base-object for config-files.
 * The module-specific config-files can extend the functionality from this class.
 *
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 */
class Config extends \KernelLoader
{
    /**
     * The default action
     *
     * @var    string
     */
    protected $defaultAction = 'index';

    /**
     * The disabled actions
     *
     * @var    array
     */
    protected $disabledActions = array();

    /**
     * The disabled AJAX-actions
     *
     * @var    array
     */
    protected $disabledAJAXActions = array();

    /**
     * The current loaded module
     *
     * @var    string
     */
    protected $module;

    /**
     * All the possible actions
     *
     * @var    array
     */
    protected $possibleActions = array();

    /**
     * All the possible AJAX actions
     *
     * @var    array
     */
    protected $possibleAJAXActions = array();

    /**
     * @param KernelInterface $kernel
     * @param string          $module The module wherefore this is the configuration-file.
     */
    public function __construct(KernelInterface $kernel, $module)
    {
        parent::__construct($kernel);

        $this->module = (string) $module;

        // check if model exists
        if (is_file(FRONTEND_MODULES_PATH . '/' . $this->getModule() . '/engine/model.php')) {
            // the model exists, so we require it
            require_once FRONTEND_MODULES_PATH . '/' . $this->getModule() . '/engine/model.php';
        }

        // read the possible actions based on the files
        $this->setPossibleActions();
    }

    /**
     * Get the default action
     *
     * @return string
     */
    public function getDefaultAction()
    {
        return $this->defaultAction;
    }

    /**
     * Get the current loaded module
     *
     * @return string
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * Get the possible actions
     *
     * @return array
     */
    public function getPossibleActions()
    {
        return $this->possibleActions;
    }

    /**
     * Get the possible AJAX actions
     *
     * @return array
     */
    public function getPossibleAJAXActions()
    {
        return $this->possibleAJAXActions;
    }

    /**
     * Set the possible actions, based on files in folder.
     * You can disable action in the config file. (Populate $disabledActions)
     */
    protected function setPossibleActions()
    {
        // build path to the module
        $frontendModulePath = FRONTEND_MODULES_PATH . '/' . $this->getModule();
        $fs = new Filesystem();

        if ($fs->exists($frontendModulePath . '/actions')) {
            // get regular actions
            $finder = new Finder();
            $finder->name('*.php');
            foreach ($finder->files()->in($frontendModulePath . '/actions') as $file) {
                $action = $file->getBasename('.php');
                if (!in_array($action, $this->disabledActions)) {
                    $this->possibleActions[$file->getBasename()] = $action;
                }
            }
        }

        if ($fs->exists($frontendModulePath . '/ajax')) {
            // get ajax-actions
            $finder = new Finder();
            $finder->name('*.php');
            foreach ($finder->files()->in($frontendModulePath . '/ajax') as $file) {
                $action = $file->getBasename('.php');
                if (!in_array($action, $this->disabledAJAXActions)) {
                    $this->possibleAJAXActions[$file->getBasename()] = $action;
                }
            }
        }
    }
}
