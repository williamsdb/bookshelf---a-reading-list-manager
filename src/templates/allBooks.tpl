{include file="header.tpl"}

<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">All Books</h3>
  <button class="btn btn-outline-secondary position-relative" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas" aria-controls="filterOffcanvas">
    <i class="bi bi-funnel-fill"></i> Filters
    <span id="filter-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary d-none">0</span>
  </button>
</div>

<!-- Filter offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="filterOffcanvasLabel"><i class="bi bi-funnel-fill"></i> Filters</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column">
    <div class="flex-grow-1">
      <div class="mb-3">
        <label for="listSelectHome" class="form-label fw-semibold">List</label>
        <select id="listSelectHome" name="list_id" class="form-select">
          <option value="0"{if $defaultListId == 0} selected{/if}>All Books</option>
          {foreach from=$lists item=list}
            <option value="{$list.id}"{if $list.id == $defaultListId} selected{/if}>{$list.name}</option>
          {/foreach}
        </select>
      </div>
      <div class="mb-3">
        <label for="filter-status" class="form-label fw-semibold">Status</label>
        <select id="filter-status" class="form-select">
          <option value="">All</option>
          <option value="Read">Read</option>
          <option value="Not Read">Not Read</option>
          <option value="Reading">Reading</option>
        </select>
      </div>
      <div class="mb-3">
        <label for="sortByDate" class="form-label fw-semibold">Date column</label>
        <select id="sortByDate" class="form-select">
          <option value="0"{if $sortBy == 0} selected{/if}>Date Read</option>
          <option value="1"{if $sortBy == 1} selected{/if}>Date Added</option>
        </select>
      </div>
      <hr>
      <div class="mb-3">
        <label for="fc-title" class="form-label fw-semibold">Title</label>
        <input type="text" id="fc-title" class="form-control" placeholder="Search title" value="{$title|escape}">
      </div>
      <div class="mb-3">
        <label for="fc-author" class="form-label fw-semibold">Author</label>
        <input type="text" id="fc-author" class="form-control" placeholder="Search author" value="{$author|escape}">
      </div>
      <div class="mb-3">
        <label for="fc-date" class="form-label fw-semibold">{if $sortBy == 0}Date Read{else}Date Added{/if}</label>
        <input type="text" id="fc-date" class="form-control" placeholder="e.g. 2024" value="{$dateRead|escape}">
      </div>
      <div class="mb-3">
        <label for="fc-rating" class="form-label fw-semibold">Rating</label>
        <input type="text" id="fc-rating" class="form-control" placeholder="e.g. 4" value="{$rating|escape}">
      </div>
      <div class="mb-3">
        <label for="fc-format" class="form-label fw-semibold">Format</label>
        <input type="text" id="fc-format" class="form-control" placeholder="Search format" value="{$format|escape}">
      </div>
      <div class="mb-3">
        <label for="fc-source" class="form-label fw-semibold">Source</label>
        <input type="text" id="fc-source" class="form-control" placeholder="Search source" value="{$source|escape}">
      </div>
    </div>
    <div class="border-top pt-3 mt-3 d-flex gap-2">
      <button class="btn btn-primary flex-grow-1" data-bs-dismiss="offcanvas">
        <i class="bi bi-check-lg"></i> Apply Filters
      </button>
      <button id="clear-filters" class="btn btn-outline-danger flex-grow-1">
        <i class="bi bi-x-circle"></i> Clear All
      </button>
    </div>
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
  </thead>
  <tbody>
      {section name=all loop=$books}
      <tr>
          <td>
            <div class="d-flex align-items-start">
              {if $books[all].source == "Plex" ||  $books[all].source == "Calibre"}
                  <img data-src="/cache/{$books[all].id}.jpg" src="/assets/brand/no-cover.png" width="40" alt="Cover" class="book-cover-small book-cover-lazy me-2 flex-shrink-0" />
              {else}
                {if $books[all].isbn}
                  <img data-src="https://covers.openlibrary.org/b/isbn/{$books[all].isbn}-S.jpg" src="/assets/brand/no-cover.png" alt="Cover" class="book-cover-small book-cover-lazy me-2 flex-shrink-0" />
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
                <div class="d-md-none small text-muted mt-1">{$books[all].author}</div>
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