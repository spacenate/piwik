<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\PrivacyManager;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\DataAccess\RawLogDao;
use Piwik\Date;
use Piwik\Db;
use Piwik\Log;
use Piwik\LogPurger;
use Piwik\Piwik;

/**
 * Purges the log_visit, log_conversion and related tables of old visit data.
 */
class LogDataPurger
{
    /**
     * The max set of rows each table scan select should query at one time.
     */
    public static $selectSegmentSize = 100000;

    /**
     * The number of days after which log entries are considered old.
     */
    private $deleteLogsOlderThan;

    /**
     * The number of rows to delete per DELETE query.
     */
    private $maxRowsToDeletePerQuery;

    /**
     * TODO
     *
     * @var LogPurger
     */
    private $logPurger;

    /**
     * TODO
     *
     * @var RawLogDao
     */
    private $rawLogDao;

    /**
     * TODO
     *
     * @var int
     */
    private $logIterationStepSize = 1000; // TODO: make configurable via constructor

    /**
     * Constructor.
     *
     * @param int $deleteLogsOlderThan The number of days after which log entires are considered old.
     *                                 Visits and related data whose age is greater than this number
     *                                 will be purged.
     * @param int $maxRowsToDeletePerQuery The maximum number of rows to delete in one query. Used to
     *                                     make sure log tables aren't locked for too long.
     */
    public function __construct($deleteLogsOlderThan, $maxRowsToDeletePerQuery, LogPurger $logPurger = null, RawLogDao $rawLogDao = null)
    {
        $this->deleteLogsOlderThan = $deleteLogsOlderThan;
        $this->maxRowsToDeletePerQuery = $maxRowsToDeletePerQuery;
        $this->logPurger = $logPurger ?: StaticContainer::get('Piwik\LogPurger');
        $this->rawLogDao = $rawLogDao ?: StaticContainer::get('Piwik\DataAccess\RawLogDao');
    }

    /**
     * Purges old data from the following tables:
     * - log_visit
     * - log_link_visit_action
     * - log_conversion
     * - log_conversion_item
     * - log_action
     */
    public function purgeData()
    {
        $dateStart = Date::factory("today")->subDay($this->deleteLogsOlderThan); // TODO: move logic to constructor
        $conditions = array(
            array('visit_last_action_time', '<', $dateStart->getDatetime())
        );

        $logPurger = $this->logPurger;
        $this->rawLogDao->forAllLogs('log_visit', array('idvisit'), $conditions, $this->logIterationStepSize, function ($rows) use ($logPurger) {
            $ids = array_map('reset', $rows);
            $logPurger->deleteVisits($ids);
        });

        $logTables = self::getDeleteTableLogTables();

        // delete unused actions from the log_action table (but only if we can lock tables)
        if (Db::isLockPrivilegeGranted()) {
            $this->rawLogDao->deleteUnusedLogActions();
        } else {
            $logMessage = get_class($this) . ": LOCK TABLES privilege not granted; skipping unused actions purge";
            Log::warning($logMessage);
        }

        // optimize table overhead after deletion // TODO: logs:delete command should allow optimization
        Db::optimizeTables($logTables);
    }

    /**
     * Returns an array describing what data would be purged if purging were invoked.
     *
     * This function returns an array that maps table names with the number of rows
     * that will be deleted.
     *
     * @return array
     *
     * TODO: purge estimate should ideally not use idvisit, but we have to wait until performance tests are done to
     *       really test this.
     * TODO: let's move PrivacyManagerTest to PrivacyManager plugin
     */
    public function getPurgeEstimate()
    {
        $result = array();

        // deal w/ log tables that will be purged
        $maxIdVisit = $this->getDeleteIdVisitOffset();
        if (!empty($maxIdVisit)) {
            foreach ($this->getDeleteTableLogTables() as $table) {
                // getting an estimate for log_action is not supported since it can take too long
                if ($table != Common::prefixTable('log_action')) {
                    $rowCount = $this->getLogTableDeleteCount($table, $maxIdVisit);
                    if ($rowCount > 0) {
                        $result[$table] = $rowCount;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * get highest idVisit to delete rows from
     * @return string
     */
    private function getDeleteIdVisitOffset()
    {
        $logVisit = Common::prefixTable("log_visit");

        // get max idvisit
        $maxIdVisit = Db::fetchOne("SELECT MAX(idvisit) FROM $logVisit");
        if (empty($maxIdVisit)) {
            return false;
        }

        // select highest idvisit to delete from
        $dateStart = Date::factory("today")->subDay($this->deleteLogsOlderThan);
        $sql = "SELECT idvisit
		          FROM $logVisit
		         WHERE '" . $dateStart->toString('Y-m-d H:i:s') . "' > visit_last_action_time
		           AND idvisit <= ?
		           AND idvisit > ?
		      ORDER BY idvisit DESC
		         LIMIT 1";

        return Db::segmentedFetchFirst($sql, $maxIdVisit, 0, -self::$selectSegmentSize);
    }

    private function getLogTableDeleteCount($table, $maxIdVisit)
    {
        $sql = "SELECT COUNT(*) FROM $table WHERE idvisit <= ?";
        return (int) Db::fetchOne($sql, array($maxIdVisit));
    }

    // let's hardcode, since these are not dynamically created tables
    public static function getDeleteTableLogTables()
    {
        $result = Common::prefixTables('log_conversion',
            'log_link_visit_action',
            'log_visit',
            'log_conversion_item');
        if (Db::isLockPrivilegeGranted()) {
            $result[] = Common::prefixTable('log_action');
        }
        return $result;
    }

    /**
     * Utility function. Creates a new instance of LogDataPurger with the supplied array
     * of settings.
     *
     * $settings must contain values for the following keys:
     * - 'delete_logs_older_than': The number of days after which log entries are considered
     *                             old.
     * - 'delete_logs_max_rows_per_query': Max number of rows to DELETE in one query.
     *
     * @param array $settings Array of settings
     * @param bool $useRealTable
     * @return \Piwik\Plugins\PrivacyManager\LogDataPurger
     */
    public static function make($settings, $useRealTable = false)
    {
        return new LogDataPurger(
            $settings['delete_logs_older_than'],
            $settings['delete_logs_max_rows_per_query']
        );
    }
}
