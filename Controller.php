<?php
namespace Piwik\Plugins\BackfillGoals;

use Piwik\Db;
use Piwik\Piwik;
use Piwik\Site;
use Piwik\Common;
use Piwik\API\Request;
use Piwik\Period\Range;
use Piwik\Plugins\SitesManager\API;

/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

class Controller extends \Piwik\Plugin\Controller
{
    public function executeQueries()
    {
        // Ensure only authorized users can execute the queries
        Piwik::checkUserHasSomeAdminAccess();

        // Retrieve the site ID and date range from the request
        $siteId = Common::getRequestVar('siteId', null, 'int');
        $segment = Request::getRawSegmentFromRequest();
        $dateRange = Common::getRequestVar('dateRange', '', 'string');

        // Get the database connection
        $db = Db::get();

        // Get the table prefix dynamically
        $prefix = \Piwik\Config::getInstance()->database['tables_prefix'];

        // If no site ID is provided, fetch all site IDs
        if (!$siteId || $siteId === -1) {
            $allSites = API::getInstance()->getAllSites();
            $siteIds = array_map(function($site) {
                return $site['idsite'];
            }, $allSites);
        } else {
            $siteIds = [$siteId];
        }

        // If no date range is provided, fetch the complete date range
        if (!$dateRange) {
            $dateRange = [];
            list($minDate, $maxDate) = Site::getMinMaxDateAcrossWebsites(implode(',', $siteIds));
            $months  = Common::getRequestVar('months', '', 'string');
            if ($months > 0) $minDate = $maxDate->subMonth($months);

            $range = new Range('day', $minDate->toString() . ',' . $maxDate->toString());
            foreach ($range->getSubperiods() as $subPeriod) {
                $dateRange[] = $subPeriod->getDateStart();
            }

            $dateOutput = explode(' ', $minDate)[0] . ' to ' . explode(' ', $maxDate)[0];
        } else {
            $dateOutput = str_replace(",", ", ", $dateRange);
        }

        // Define the base queries
        $baseInsertQuery = "
            INSERT IGNORE INTO `{$prefix}log_conversion` (`idvisit`, `idsite`, `idvisitor`, `server_time`, `idaction_url`, `idlink_va`, `idgoal`, `buster`, `idorder`, `items`, `url`, `visitor_returning`, `visitor_count_visits`, `referer_keyword`, `referer_name`, `referer_type`, `config_device_brand`, `config_device_model`, `config_device_type`, `location_city`, `location_country`, `location_latitude`, `location_longitude`, `location_region`, `revenue`, `revenue_discount`, `revenue_shipping`, `revenue_subtotal`, `revenue_tax`, `visitor_seconds_since_first`, `visitor_seconds_since_order`, `custom_dimension_1`, `custom_dimension_2`, `custom_dimension_3`, `custom_dimension_4`, `custom_dimension_5`, `config_browser_name`, `config_client_type`)
            SELECT va.`idvisit`, va.idsite, va.`idvisitor`, va.`server_time`, va.`idaction_url`, va.idlink_va, g.`idgoal`, va.idlink_va 'buster', null 'idorder', null 'items', CONCAT('https://www.', a.name) url, v.`visitor_returning`, v.`visitor_count_visits`, v.`referer_keyword`, v.`referer_name`, v.`referer_type`, v.`config_device_brand`, v.`config_device_model`, v.`config_device_type`, v.`location_city`, v.`location_country`, v.`location_latitude`, v.`location_longitude`, v.`location_region`, 0 'revenue', null 'revenue_discount', null 'revenue_shipping', null 'revenue_subtotal', null 'revenue_tax', v.`visitor_seconds_since_first`, v.`visitor_seconds_since_order`, v.`custom_dimension_1`, v.`custom_dimension_2`, v.`custom_dimension_3`, v.`custom_dimension_4`, v.`custom_dimension_5`, v.`config_browser_name`, v.`config_client_type`
            FROM `{$prefix}log_link_visit_action` va 
            JOIN `{$prefix}log_action` a ON a.type = 1 AND a.`idaction` = va.`idaction_url`
            JOIN {$prefix}goal g ON g.deleted = 0 AND g.`match_attribute` = 'url' AND g.pattern_type = 'regex' AND CONCAT('https://www.', a.name) REGEXP g.pattern
            JOIN `{$prefix}log_visit` v ON v.idvisit = va.`idvisit`
            LEFT JOIN `{$prefix}log_conversion` lc ON va.`idvisit` = lc.`idvisit` AND va.idsite = lc.idsite AND va.`idvisitor` = lc.`idvisitor` AND va.`server_time` = lc.`server_time` AND g.`idgoal` = lc.`idgoal`
            WHERE va.idsite = :siteId AND lc.idvisit IS NULL
            ORDER BY va.idvisit ASC";


        $updateQuery = "UPDATE `{$prefix}log_visit` v JOIN  `{$prefix}log_conversion` c ON c.idvisit = v.idvisit SET v.`visit_goal_converted` = 1";

        $currentUrl = \Piwik\Url::getCurrentQueryStringWithParametersModified(array(
            'module' => 'CoreHome',
            'action' => 'index',
            'category' => 'Goals_Goals',
            'subcategory' => 'Backfill Goals',
        ));

        try {
            //$executedQueries = []; // Array to store executed queries

            // Execute the queries for each site ID
            foreach ($siteIds as $id) {
                $stmt = $db->prepare($baseInsertQuery);
                $stmt->bindParam(':siteId', $id, \PDO::PARAM_INT, 0);
                $stmt->execute();
                
                // Store the query for debugging
                //$debugQuery = str_replace(':siteId', $id, $baseInsertQuery);
                //$executedQueries[] = $debugQuery;

                $db->exec($updateQuery);
                
                // Invalidate old reports for the current site ID
                \Piwik\API\Request::processRequest('CoreAdminHome.invalidateArchivedReports', [
                    'format'  => 'json',
                    'idSites' => [$id],
                    'period'  => false,
                    'dates' => is_array($dateRange) ? implode(',', $dateRange) : $dateRange,
                    'segment' => $segment
                ]);
            }

            // Force the archiving process (optional)
            \Piwik\API\Request::processRequest('CoreAdminHome.runScheduledTasks');

            // Redirect back to the initial page with a success message
            return json_encode([
                'type' => 'success',
                'message' => "Goals backfilled and reports invalidated successfully for the following dates: {$dateOutput}",
                //'queries' => $executedQueries
            ]);

        } catch (\Exception $e) {
            // Redirect back to the initial page with an error message
            return json_encode([
                'type' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage(),
                //'queries' => $executedQueries
            ]);
        }
    }

}
