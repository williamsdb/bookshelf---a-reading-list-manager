{include file="header.tpl"}

<h3>All Books</h3>

    <div class="container my-3">
        <form method="get" action="">
            <div class="row">
                <div class="col-auto">
                    <label for="listSelect" style="margin-top: 7px;">Filter by list: </label>
                </div>
                <div class="col-auto">
                    <select id="listSelect" name="list_id" class="form-select">
                            <option value="0"{if $defaultListId == 0} selected{/if}>All Books</option>
                        {foreach from=$lists item=list}
                            <option value="{$list.id}"{if $list.id == $defaultListId} selected{/if}>{$list.name}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="col-auto">
                    <label for="listSelect" style="margin-top: 7px;">Filter by Status: </label>
                </div>
                <div class="col-auto">
                  <select id="filter-status" class="form-select">
                    <option value="">All</option>
                    <option value="Read">Read</option>
                    <option value="Not Read">Not Read</option>
                    <option value="Reading">Reading</option>
                  </select>
                </div>
            </div>
        </form>
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
      <th class="desktop tablet" width="15%">Date Read</th>
      <th class="desktop tablet" width="10%">Rating</th>
      <th class="desktop tablet">Format</th>
      <th class="desktop tablet">Source</th>
      <th class="desktop tablet">Status Sort</th>
    </tr>
    <tr>
      <th><input type="text" placeholder="Search Title" class="form-control" value="{$title|escape}" /></th>
      <th><input type="text" placeholder="Search Author" class="form-control" value="{$author|escape}" /></th>
      <th><input type="text" placeholder="Search Date Read" class="form-control" value="{$dateRead|escape}" /></th>
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
            <a href="/viewDetails/{$books[all].id}">
              {if $truncateAfterColon}
                {$books[all].title|regex_replace:"/[[:space:]]*:.*/":""}
              {else}
                {if $books[all].title != ""}
                  {$books[all].title}
                {else}
                  <span class="text-muted">Unknown Title</span>
                {/if}
              {/if}
            </a>
            {if $books[all].series}
              <br /><span class="badge bg-secondary">{$books[all].series} {if $books[all].seriesPosition} (#{$books[all].seriesPosition}){/if}</span>
            {/if}
          </td>
          <td><a href="?author={$books[all].author}">{$books[all].author}</a></td>
          <td>{$books[all].dateRead}</td>
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