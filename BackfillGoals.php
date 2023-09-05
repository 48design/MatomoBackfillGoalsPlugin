<?php

namespace Piwik\Plugins\BackfillGoals;

use Piwik\Plugin;

class BackfillGoals extends Plugin
{
    public function registerWidgets()
    {
        // Ensure only authorized users can execute the queries
        Piwik::checkUserHasSomeAdminAccess();
        
        WidgetsList::add('Goals_Goals', 'Backfill Conversions', 'BackfillGoals', 'getBackfillConversions');
    }

    public function registerEvents()
    {
        return array(
            // You can specify events your plugin listens to here.
        );
    }
}
