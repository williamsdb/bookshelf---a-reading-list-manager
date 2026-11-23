{include file="header.tpl"}
<h1>Find missing covers</h1>
<p>Check existing books and audiobooks for artwork. Completed covers are reused. Imported artwork is resized to fit within 100 × 150 pixels; Open Library covers are displayed directly without storing images on this server.</p>
<p>If Plex is switched off, its missing covers are deferred for the next run. Existing thumbnails remain available. You can pause or close this page and resume later in this browser tab.</p>
{if !$coverGdAvailable}
<div class="alert alert-warning">PHP's GD image extension is unavailable. Enable it to create compact Plex and Calibre thumbnails. Existing artwork and Open Library covers can still be displayed.</div>
{/if}
<button class="btn btn-primary" type="button" id="cover-start">Start catch-up</button>
<button class="btn btn-secondary" type="button" id="cover-stop" disabled>Pause</button>
<p id="cover-progress" class="mt-3" role="status" aria-live="polite">Ready to check your library.</p>
<progress id="cover-progress-bar" max="100" value="0" style="width:100%" aria-label="Cover catch-up progress" hidden></progress>
<ul id="cover-results" class="mt-3"></ul>
<p>Books without a unique title-and-author match are left for checking. Add an ISBN or an Open Library book link in their details to improve the next lookup. Missing covers are checked again after a week; temporary failures are retried sooner.</p>
{if $coverNeedsReview}
<h2>Books to check</h2>
<ul>
{foreach from=$coverNeedsReview item=coverBook}
<li><a href="/viewDetails/{$coverBook.id}">{$coverBook.title|escape:'html'}</a> — {$coverBook.author|escape:'html'}</li>
{/foreach}
</ul>
<p>Reload this page after catch-up to update this list.</p>
{/if}
{include file="footer.tpl"}
