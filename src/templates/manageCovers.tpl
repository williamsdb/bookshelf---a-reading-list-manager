{include file="header.tpl"}
<h1>Find missing covers</h1>
<p>Check existing books and audiobooks for artwork. Completed covers are reused. Imported artwork is resized to fit within 100 × 150 pixels; Open Library covers are displayed directly without storing images on this server.</p>
<p>If Plex is switched off, its missing covers are deferred for the next run. Existing thumbnails remain available. You can pause or close this page and resume later in this browser tab.</p>
{if !$coverGdAvailable}
<div class="alert alert-warning">PHP's GD image extension is unavailable. Enable it to create compact Plex and Calibre thumbnails. Existing artwork and Open Library covers can still be displayed.</div>
{/if}
<p>Amazon and Audible links are not used to fetch artwork. Books with those links are looked up on Open Library using their ISBN, or their title and author. A fresh Open Library lookup can take up to 20 seconds.</p>
<button class="btn btn-primary" type="button" id="cover-start">Start catch-up</button>
<button class="btn btn-secondary" type="button" id="cover-stop" disabled>Pause</button>
<p id="cover-progress" class="mt-3" role="status" aria-live="polite">Ready to check your library.</p>
<progress id="cover-progress-bar" max="100" value="0" style="width:100%" aria-label="Cover catch-up progress" hidden></progress>
<p>For a failed lookup, expand <strong>Lookup diagnostics</strong> below to see the actual request duration, connection timings and error code. These details are also saved in Bookshelf, even if the server’s PHP error log is empty.</p>
<ul id="cover-results" class="mt-3"></ul>
<p>Books without a confident cover match are listed below. You can improve their identifying details or upload artwork from the book details page. Missing covers are checked again after a week; temporary failures are retried sooner.</p>
{if $coverFailures}
<h2>Recent failed lookups</h2>
<p>These are saved results from earlier attempts, not new requests. Check the timestamp in the diagnostics. Run catch-up again to retry.</p>
<ul>
{foreach from=$coverFailures item=coverFailure}
<li><a href="/viewDetails/{$coverFailure.id}">{$coverFailure.title|escape:'html'}</a> — {$coverFailure.message|escape:'html'}
<details><summary>Previous lookup diagnostics</summary><pre class="small mt-2 text-wrap">{$coverFailure.diagnostics|escape:'html'}</pre></details></li>
{/foreach}
</ul>
{/if}
{if $coverNeedsReview}
<h2>Books needing cover details</h2>
<p>Bookshelf could not find one confident title-and-author match for these books. No cover has been selected for you to approve. To help it find the right artwork:</p>
<ol>
<li>Open a book below and check its title and author.</li>
<li>Add its ISBN or the correct Open Library work/edition link, or choose <strong>Upload a cover</strong> on the book details page.</li>
<li>Click <strong>Save</strong>. For changed book details, run catch-up again to retry the lookup. An uploaded cover is available immediately after saving.</li>
</ol>
<p>For Plex audiobooks, match the album title and artist in Plex, or upload artwork yourself. Re-importing Plex metadata can also supply its artwork reference. Offline Plex covers are deferred separately and do not mean that your book details are wrong.</p>
<ul>
{foreach from=$coverNeedsReview item=coverBook}
<li><a href="/viewDetails/{$coverBook.id}">{$coverBook.title|escape:'html'}</a> — {$coverBook.author|escape:'html'}</li>
{/foreach}
</ul>
<p>Reload this page after catch-up to update this list.</p>
{/if}
{include file="footer.tpl"}
