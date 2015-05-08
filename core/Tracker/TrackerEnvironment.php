<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tracker;

use Piwik\Application\Environment;
use Piwik\Application\Kernel\PluginList;

/**
 * TODO
 */
class TrackerEnvironment extends Environment
{
    public function __construct($definitions = array())
    {
        parent::__construct('tracker', $definitions);
    }

    protected function getPluginList()
    {
        return new TrackerPluginList($this->getGlobalSettingsCached());
    }
}