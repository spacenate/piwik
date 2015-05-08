<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tracker;

use Piwik\Application\Application;
use Piwik\Application\Environment;

/**
 * TODO
 */
class TrackerApplication extends Application
{
    public function __construct($definitions = array())
    {
        parent::__construct(new Environment('tracker'), $definitions);
    }

    /**
     * TODO
     *
     * @param $params
     */
    public function track($params)
    {
        // TODO
    }
}