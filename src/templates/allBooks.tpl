{include file="header.tpl"}

<h3>All Books</h3>

<table class="table table-striped" id="allBooks">
<thead>
  <tr>
    <th>Title</th>
    <th>Author</th>
    <th>Date Read</th>
    <th>Rating</th>
    <th>Status</th>
    <th>Status Sort</th>
  </tr>
  <tr>
    <th><input type="text" placeholder="Search Title" class="form-control" value="{$title|escape}" /></th>
    <th><input type="text" placeholder="Search Author" class="form-control" value="{$author|escape}" /></th>
    <th><input type="text" placeholder="Search Date Read" class="form-control" value="{$dateRead|escape}" /></th>
    <th><input type="text" placeholder="Search Rating" id="rating-status" class="form-control" value="{$rating|escape}" /></th>
    <th>
      <select id="filter-status" class="form-select">
        <option value="">All</option>
        <option value="Read">Read</option>
        <option value="Not Read">Not Read</option>
        <option value="Reading">Reading</option>
      </select>
    </th>
    <th>
        &nbsp;
    </th>
  </tr>
</thead>
<tbody>
    {section name=all loop=$books}
    <tr>
        <td>
          <a href="/viewDetails/{$books[all].id}"><span class="truncate">{$books[all].title}</span></a>
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
            <select name="status_{$books[all].id}" id="statusChange_{$books[all].id}" class="form-select" onchange="changeStatus(this);">
                <option value="0"{if $books[all].read == 0} selected{/if}>Not Read</option>
                <option value="1"{if $books[all].read == 1} selected{/if}>Reading</option>
                <option value="2"{if $books[all].read == 2} selected{/if}>Read</option>
           </select>
        </td>
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
      <th>Status</th>
      <th>Status Sort</th>
    </tr>
</tfoot>
</table>

{include file="footer.tpl"}