{include file="header.tpl"}
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <h3>Reading statistics</h3>

  <p>You have a total of <strong>{$totalBooks}</strong> books in your collection with {$readBooks} books read.</p>

  <h4>Books read by year</h4>

  <canvas id="byYear"></canvas>
  <script>
      const ctx = document.getElementById('byYear').getContext('2d');
      const byYear = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {$labels},
            datasets: [{
                label: 'Books Read',
                data: {$data},
                backgroundColor: '#0d6efd',
                borderColor: '#0d6efd',
                borderWidth: 3
            }]
        },
        options: {
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Year' // Label for X-axis
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Books Read' // Label for Y-axis
                    },
                    beginAtZero: true // Start the Y-axis at zero
                }
            }
        }
    });
  </script>

  <h4>Top 10 authors</h4>
  <table class="table table-striped">
      <thead>
          <tr>
              <th style="text-align: right">#</th>
              <th>Author</th>
              <th style="text-align: right">Books Read</th>
          </tr>
      </thead>
      <tbody>
          {assign var="count" value=1}
          {foreach from=$topAuthors item=author}
          <tr>
              <td width="5%" align="right">{$count}</td>
              <td><a href="/?author={$author.name|escape:'url'}">{$author.name}</a></td>
              <td align="right">{$author.count}</td>
          </tr>
          {assign var="count" value=$count+1}
          {/foreach}
      </tbody>
  </table>

  <h4>Books read by format</h4>

  <canvas id="byFormat"></canvas>
  <script>
      const cty = document.getElementById('byFormat').getContext('2d');
      const byFormat = new Chart(cty, {
        type: 'bar',
        data: {
            labels: {$labels},
            datasets: [{
                label: 'Physical',
                data: {$physical},
                backgroundColor: '#0d6efd',
                borderColor: '#0d6efd',
                borderWidth: 3
            },{
                label: 'Ebooks',
                data: {$ebooks},
                backgroundColor: '#C74549',
                borderColor: '#C74549',
                borderWidth: 3
            },{
                label: 'Audiobooks',
                data: {$audiobooks},
                backgroundColor: '#E7B53D',
                borderColor: '#E7B53D',
                borderWidth: 3
            }]
        },
        options: {
            scales: {
                x: {
                    stacked: true,
                    title: {
                        text: 'Year' // Label for X-axis
                    }
                },
                y: {
                    stacked: true,
                    title: {
                        text: 'Books read by format' // Label for Y-axis
                    },
                    beginAtZero: true // Start the Y-axis at zero
                }
            }
        }
    });
  </script>

{include file="footer.tpl"}