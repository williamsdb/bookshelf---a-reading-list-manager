{include file="header.tpl"}

<h3>Fetch audiobooks from Plex</h3>

<p>Click the button below to scan your Plex library for audiobooks. This will add any new books to your reading list.<br />Depending on the size of your library, this may take some time.</p>
<form action="/fetchPlexAction" method="post">
    <button type="submit" class="btn btn-primary">Fetch from Plex</button>
</form>

{include file="footer.tpl"}