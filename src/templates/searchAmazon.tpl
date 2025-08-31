{include file="header.tpl"}

<h3>Search to find a book by Amazon link</h3>
    <p id="h2title">Search by Amazon link to add to your reading list</p>
    <form method="post" id="amazon-form" action="/searchAmazonResults">
        <input
            type="text"
            name="url"
            class="form-control"
            placeholder="Enter Amazon link"
            style="width: 50%;"
            required
        />
        <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Search</button>
    </form>

{include file="footer.tpl"}