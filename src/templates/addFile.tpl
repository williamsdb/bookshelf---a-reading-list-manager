{include file="header.tpl"}

<h3>Upload a file</h3>
<p>You can add a list of multiple books to your Bookshelf by uploading a file. The contents will be processed and added to your library.</p>

<form id="myDropzoneForm" action="/processFile" method="post" enctype="multipart/form-data">
  <!-- Hidden file input that Dropzone will manage -->
  <input type="file" name="file" id="fileInput" style="display:none" multiple>
  
  <!-- Dropzone container -->
  <div class="dropzone" id="myDropzone"></div>

  <button type="submit" id="submit-btn" class="btn btn-primary" style="margin-top: 1rem;">Process Files</button>
</form>

    <p style="margin-top: 1rem;">Note that data file in any one of the following formats will be accepted:</p>
    <ul>
        <li>CSV - a standard format CSV file. See <a href="https://github.com/williamsdb/bookshelf" target="_blank">here</a> for details</li>
        <li>Audible - from an <a href="https://openaudible.org/documentation#exporting_all_audiobook_data" target="_blank">OpenAudible export</a></li>
        <li>Kindle - from a <a href="https://chromewebstore.google.com/detail/amazonkindle-book-list-do/cnmmnejiklbbkapmjegmldhaejjiejbo" target="_blank">Kindle Book List Downloader export</a></li>
    </ul>
{include file="footer.tpl"}