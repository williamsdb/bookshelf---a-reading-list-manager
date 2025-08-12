{include file="header.tpl"}

<h3>When did I last {$activityName}?</h3>

<table class="table table-striped">
    <tbody>
        <tr><td><strong>Last triggered</strong></td><td>{$triggers[$triggers|count - 1].timestamp|date_format_tz:"Y-m-d H:i:s":$smarty.const.TZ}</td></tr>
        <tr><td><strong>Elapsed since last triggered</strong></td><td>{$elp}</td></tr>
        <tr><td><strong>Estimated until next trigger</strong></td><td>{$enp}</td></tr>
        <tr><td><strong>Times triggered</strong></td><td>{$triggers|count}</td></tr>
        <tr><td><strong>Avg trigger interval</strong></td><td>{$avg}</td></tr>
        <tr><td><strong>Trend</strong></td><td>{$trend}</td></tr>
        <tr><td><strong>Longest interval</strong></td><td>{$lrg}</td></tr>
    </tbody>
    </table>
</table>

<button type="button" class="btn btn-primary" data-wdil="{$id}" data-bs-toggle="modal" data-bs-target="#triggerModal">Trigger</button>

<hr>
<h4 style="margin-top: 10px;">Interval change over time</h4>
{if $labels == ''}
  <p>Not enough data</p>
{else}
  <canvas id="myChart"></canvas>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
      const ctx = document.getElementById('myChart').getContext('2d');
      const myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: {$labels},
            datasets: [{
                label: 'Interval',
                data: {$data},  // These are seconds
                backgroundColor: '#0d6efd',
                borderColor: '#0d6efd',
                borderWidth: 3
            }]
        },
        options: {
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(tooltipItem) {
                            // Get the value in seconds
                            var valueInSeconds = tooltipItem.raw;

                            // Use the formatTime function to format the seconds into a human-readable format
                            return formatTime(valueInSeconds);
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Trigger Date and Time' // Label for X-axis
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Time Interval (seconds)' // Label for Y-axis
                    },
                    beginAtZero: true // Start the Y-axis at zero
                }
            }
        }
    });
  </script>
{/if}

<!-- Bootstrap Modal -->
<div class="modal fade" id="triggerModal" tabindex="-1" aria-labelledby="triggerModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="triggerModalLabel">Trigger activity</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
		<form role="form" action="/triggerActivity" method="post" id="triggerForm">
			<div class="mb-3">
				<label for="datetimePicker" class="form-label">Select Date and Time</label>
				<input type="datetime-local" class="form-control" name="dateTime" step="1" id="datetimePicker" required>
				<div id="error-message" style="color: red; display: none;">The date must be in the past.</div>
				<label for="triggerComment" class="form-label" style="margin-top: 10px;">Comment (optional)</label>
				<input type="text" class="form-control" name="comment" id="triggerComment" maxlength="50">
				<input type="hidden" class="form-control" name="activityId" id="activityId" value="{$id}">
				<input type="hidden" class="form-control" name="redirectTo" id="redirectTo" value="stats">
			</div>
		</form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="triggerButton">Save</button>
      </div>
    </div>
  </div>
</div>

<hr>
<h4>Last 10 trigger dates</h4>

<table class="table table-striped">
    <thead>
        <tr>
            <th>Date & time</th>
            <th>Comment</th>
            <th>Interval to previous</th>
            <th> </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{$smarty.now|date_format_tz:"Y-m-d H:i:s":$smarty.const.TZ} (Now)</td>
            <td>&nbsp;</td>
            <td>{$elp} ({$enpx})</td>
            <td>&nbsp;</td>
        </tr>
        {foreach from=$triggersReversed key=i item=trigger name=foo}
            {if $smarty.foreach.foo.index == 10}
            {break}
            {/if}
            <tr>
                <td width="20%">{$trigger.timestamp|date_format_tz:"Y-m-d H:i:s":$smarty.const.TZ}</td>
                <td>
                    {if isset($trigger.comment)}
                        {$trigger.comment}
                    {else}
                        &nbsp;
                    {/if}
                </td>
                {if $smarty.foreach.foo.index < $smarty.foreach.foo.total-1}
                    <td width="20%">{$intervals[$smarty.foreach.foo.index]}</td>
                {else}
                    <td width="20%">&nbsp;</td>
                {/if}
                <td align="right"><a href="#" onclick="confirmRedirect('/deleteTrigger/{$id}/{$i}'); return false;"><i class="bi-trash"></i></a></td>
            </tr>
            {/foreach}
    </tbody>
    <tfoot>
        <tr>
            <th>Date & time</th>
            <th>Comment</th>
            <th>Interval to previous</th>
            <th> </th>
        </tr>
    </tfoot>
    </table>
</table>

{include file="footer.tpl"}