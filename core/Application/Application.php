<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Application;

/**
 * TODO
 *
 * TODO:
 * - create base Application class
 * - create Tracker Application
 *   * use TrackerApplication in LocalTracker
 * - create Console Application
 * - create Web Application
 */
abstract class Application
{
    /**
     * @var Environment
     */
    private $environment;

    public function __construct(Environment $environment)
    {
        $this->environment = $environment;
        $this->environment->getContainer()->set('Piwik\Application\Application', $this);
    }
}