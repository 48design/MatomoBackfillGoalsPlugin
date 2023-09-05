<?php

namespace Piwik\Plugins\BackfillGoals\Widgets;

use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;
use Piwik\Common;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\Period\Range;

class GetBackfillConversions extends Widget
{   
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('Goals_Goals');
        $config->setSubcategoryId('Backfill Goals');
        $config->setName('Backfill Conversions');
        $config->setOrder(15);
    }
    
    public function render()
    {
        $currentSiteId = Common::getRequestVar('idSite', '', 'int'); // Fetch the current site ID from the URL query

        // Fetch the list of available sites
        $allSites = SitesManagerAPI::getInstance()->getAllSites();
        
        ob_start();
        ?>

        <div id='backfillMessage'></div>
        <div class="card" style="margin-top: 0px;">
            <div class="card-content">
                <h2 class="card-title">Backfill Goals</h2>
                <p class="ng-scope">
                    You can fill your goals with stats of the past. This may be relevant for example if you added new goals and want to historical logs be converted into goals and show up in the statistics.<br>At the moment, this only works with Regex-Goals!
                </p>
                <p class="ng-scope"><i>This may take some time to process if you have lots of tracking data!</i></p>
                <label>Site: 
                    <select id='siteId' style='display:block;width:fit-content;'>
                        <option value='-1'>All Sites</option>
                        <?php foreach ($allSites as $site) { ?>
                            <option value='<?= $site['idsite'] ?>' <?= ($currentSiteId == $site['idsite']) ? "selected" : "" ?>><?= $site['name'] ?></option>
                        <?php } ?>
                    </select>
                </label>
                <br/>
                <label>
                    Dates (leave empty to use all stats): 
                    <input type='text' id='dateRange' placeholder='e.g., 2021-01-01,2021-12-31' />
                </label>
                <br/>
                <button class='btn btn-primary' id='backfillButton' onclick="executeBackfill()">Backfill Goals</button>
                <div class='loadingPiwik' id='loadingSpinner' style='display: none;'>
                    <img src='plugins/Morpheus/images/loading-blue.gif' alt=''>
                    <span>Processing data...</span>
                </div>
            </div>
        </div>
        <script>
            function executeBackfill() {
                var siteIdValue = document.getElementById('siteId').value || -1;
                var dateRangeValue = document.getElementById('dateRange').value || "";

                // Hide the button and show the spinner
                var btn = document.getElementById('backfillButton');
                var spin = document.getElementById('loadingSpinner');
                btn.style.display = 'none';
                spin.style.display = 'block';

                var ajaxRequest = new XMLHttpRequest();
                ajaxRequest.onreadystatechange = function() {
                    if (this.readyState === 4) {
                        var messageDiv = document.getElementById('backfillMessage');
                        
                        // Remove existing notification classes
                        messageDiv.classList.remove('notification', 'system', 'notification-success', 'notification-error');
                        
                        if (this.status === 200) {
                            try {
                                var response = JSON.parse(this.responseText);
                                
                                // Add appropriate notification classes based on response type
                                if (response.type === 'success') {
                                    messageDiv.classList.add('notification', 'system', 'notification-success');
                                } else {
                                    messageDiv.classList.add('notification', 'system', 'notification-error');
                                    if (response.queries) console.log(response.queries);
                                }
                                
                                messageDiv.innerHTML = response.message;
                            } catch (e) {
                                messageDiv.innerHTML = "Unexpected response format.";
                                messageDiv.classList.add('notification', 'system', 'notification-error');
                            }
                        } else if (this.status === 500) {
                            var titleMatch = this.responseText.match(/<title>(.*?)<\/title>/);
                            var preMatch = this.responseText.match(/<pre[^>]*>([\s\S]*?)<\/pre>/);
                            
                            var title = titleMatch ? titleMatch[1] : 'Error';
                            var pre = preMatch ? preMatch[1] : '';

                            messageDiv.innerHTML = "Server Error: " + title + "<br>" + pre;
                            messageDiv.classList.add('notification', 'system', 'notification-error');
                        } else {
                            messageDiv.innerHTML = "An unexpected error occurred. Status code: " + this.status;
                            messageDiv.classList.add('notification', 'system', 'notification-error');
                        }

                        messageDiv.style.display = 'block';

                        // Show the button and hide the spinner once processing is done
                        btn.style.display = 'block';
                        spin.style.display = 'none';
                    }
                };
                ajaxRequest.open('GET', '?module=BackfillGoals&action=executeQueries&siteId=' + siteIdValue + '&dateRange=' + dateRangeValue, true);
                ajaxRequest.send();
            }
        </script>

        <?php
        return ob_get_clean();
    }
}
