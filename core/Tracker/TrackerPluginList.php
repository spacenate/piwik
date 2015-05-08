<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tracker;

use Piwik\Application\Kernel\PluginList;

/**
 * TODO
 */
class TrackerPluginList extends PluginList
{
    /**
     * @inheritdoc
     */
    public function getActivatedPlugins()
    {
        $allPlugins = $this->getActivatedPlugins();
        // TODO
    }
}