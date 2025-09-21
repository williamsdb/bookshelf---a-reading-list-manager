{include file="header.tpl"}

<h3>All Books</h3>

<div class="row g-3 align-items-center">
  <!-- List filter -->
  <div class="col-12 col-md-auto d-flex flex-column flex-md-row align-items-md-center">
    <label for="listSelectHome" class="form-label flex-md-shrink-0 mb-1 mb-md-0 me-md-2">Filter by list:</label>
    <select id="listSelectHome" name="list_id" class="form-select flex-fill">
      <option value="0"{if $defaultListId == 0} selected{/if}>All Books</option>
      {foreach from=$lists item=list}
        <option value="{$list.id}"{if $list.id == $defaultListId} selected{/if}>{$list.name}</option>
      {/foreach}
    </select>
  </div>

  <!-- Status filter -->
  <div class="col-12 col-md-auto d-flex flex-column flex-md-row align-items-md-center">
    <label for="filter-status" class="form-label flex-md-shrink-0 mb-1 mb-md-0 me-md-2">Filter by Status:</label>
    <select id="filter-status" class="form-select flex-fill">
      <option value="">All</option>
      <option value="Read">Read</option>
      <option value="Not Read">Not Read</option>
      <option value="Reading">Reading</option>
    </select>
  </div>

  <!-- Sort filter -->
  <div class="col-12 col-md-auto d-flex flex-column flex-md-row align-items-md-center">
    <label for="sortByDate" class="form-label flex-md-shrink-0 mb-1 mb-md-0 me-md-2">Date:</label>
    <select id="sortByDate" class="form-select flex-fill">
      <option value="0"{if $sortBy == 0} selected{/if}>Date Read</option>
      <option value="1"{if $sortBy == 1} selected{/if}>Date Added</option>
    </select>
  </div>
</div>

<div id="table-container">
  <div id="loading-spinner" style="text-align: center; padding: 20px;">
    <i class="fa fa-spinner fa-spin"></i> Loading data...
  </div>
  <table class="table table-striped" width="100%" id="allBooks" style="display: none;">
  <thead>
    <tr>
      <th class="desktop tablet mobile">Title</th>
      <th class="desktop tablet">Author</th>
      <th class="desktop tablet" width="15%">{if $sortBy == 0}Date Read{else}Date Added{/if}</th>
      <th class="desktop tablet" width="10%">Rating</th>
      <th class="desktop tablet">Format</th>
      <th class="desktop tablet">Source</th>
      <th class="desktop tablet">Status Sort</th>
    </tr>
    <tr>
      <th><input type="text" placeholder="Search Title" class="form-control" value="{$title|escape}" /></th>
      <th><input type="text" placeholder="Search Author" class="form-control" value="{$author|escape}" /></th>
      <th><input type="text" placeholder="Search {if $sortBy == 0}Date Read{else}Date Added{/if}" class="form-control" value="{$dateRead|escape}" /></th>
      <th><input type="text" placeholder="Search Rating" id="rating-status" class="form-control" value="{$rating|escape}" /></th>
      <th><input type="text" placeholder="Search Format" id="format-status" class="form-control" value="{$format|escape}" /></th>
      <th><input type="text" placeholder="Search Source" id="source-status" class="form-control" value="{$source|escape}" /></th>
      <th>
          &nbsp;
      </th>
    </tr>
  </thead>
  <tbody>
      {section name=all loop=$books}
      <tr>
          <td>
            <div class="d-flex align-items-start">
              {if $books[all].source == "Plex" ||  $books[all].source == "Calibre"}
                  <img src="/cache/{$books[all].id}.jpg" width="40" alt="Cover" class="book-cover-small me-2 flex-shrink-0" onerror="this.onerror=null;this.src='/assets/brand/no-cover.png';" />
              {else}
                {if $books[all].isbn}
                  <img src="https://covers.openlibrary.org/b/isbn/{$books[all].isbn}-S.jpg" alt="Cover" class="book-cover-small me-2 flex-shrink-0" onerror="this.onerror=null;this.src='/assets/brand/no-cover.png';" />
                {/if}
              {/if}

              <div class="flex-grow-1 text-break">
                <a href="/viewDetails/{$books[all].id}">
                  {if $books[all].title == ""}
                    Unknown Title
                  {else}
                    {if $truncateAfterColon}
                      {$books[all].title|regex_replace:"/[[:space:]]*:.*/":""}
                    {else}
                      {$books[all].title}
                    {/if}
                  {/if}
                </a>
              </div>
            </div>
            {if $books[all].series}
              <br /><span class="badge bg-secondary">{$books[all].series} {if $books[all].seriesPosition} (#{$books[all].seriesPosition}){/if}</span>
            {/if}
          </td>
          <td><a href="?author={$books[all].author}">{$books[all].author}</a></td>
          <td>{if $sortBy == 0}{$books[all].dateRead|date_format:"%Y-%m-%d"}{else}{$books[all].dateAdded|date_format:"%Y-%m-%d"}{/if}</td>
          <td>
            {if $books[all].rating}
              <span class="d-none rating-value">{$books[all].rating}</span>
              {math equation="floor(x)" x=$books[all].rating assign="fullStars"}
              {assign var="halfStar" value=($books[all].rating - $fullStars >= 0.5)}
              {assign var="emptyStars" value=5 - $fullStars - ($halfStar?1:0)}
              {section name=star loop=$fullStars}
                <span class="text-warning"><i class="bi bi-star-fill"></i></span>
              {/section}
              {if $halfStar}
                <span class="text-warning"><i class="bi bi-star-half"></i></span>
              {/if}
            {else}
                <span class="text-muted">No rating</span>
            {/if}
          </td>
          <td width="12%">
            {if $books[all].format}
              {$books[all].format}
            {else}
              <span class="text-muted">Unknown Format</span>
            {/if}
          </td>
          <td>{$books[all].source}</td>
          <td id="statusSort_{$books[all].id}">
              {if $books[all].read == 0}Not Read
              {elseif $books[all].read == 1}Reading
              {elseif $books[all].read == 2}Read
              {/if}
          </td>
      </tr>
      {/section}
  </tbody>
  <tfoot>
      <tr>
        <th>Title</th>
        <th>Author</th>
        <th>Date Read</th>
        <th>Rating</th>
        <th>Format</th>
        <th>Source</th>
        <th>Status Sort</th>
      </tr>
  </tfoot>
  </table>
</div>

{include file="footer.tpl"}