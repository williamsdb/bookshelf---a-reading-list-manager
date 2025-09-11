// the confirmation pop-up on things like delete
function confirmRedirect(url) {
  if (confirm("Are you sure you want to proceed?")) {
    window.location.href = url;
  }
}

// display the trigger modal dialog
if (document.getElementById("myDropzone")) {
  Dropzone.autoDiscover = false;

  var myDropzone = new Dropzone("#myDropzone", {
    url: "/processFile",
    paramName: "file",
    autoProcessQueue: false,
    paramName: "file", // The name that will be used to transfer the file
    maxFilesize: 5, // MB
    acceptedFiles: ".csv",
    dictDefaultMessage: "Drop files or click here to upload",
    clickable: "#myDropzone", // Make whole area clickable
    init: function () {
      // When files are added, put them in the hidden input
      this.on("addedfile", function (file) {
        const dataTransfer = new DataTransfer();
        this.files.forEach((f) => dataTransfer.items.add(f));
        document.getElementById("fileInput").files = dataTransfer.files;
      });
    },
  });

  // Handle form submit
  document
    .getElementById("myDropzoneForm")
    .addEventListener("submit", function (e) {
      if (myDropzone.getQueuedFiles().length === 0) {
        e.preventDefault();
        alert("Please add files first");
      }
      // Form will submit normally with files in $_FILES
    });
}

$(document).ready(function () {
  var allBooksTable = $("#allBooks").DataTable({
    dom: '<"top">rt<"bottom"ilp><"clear">',
    lengthMenu: [5, 10, 25, 50, 100],
    pageLength: 10,
    orderCellsTop: true,
    fixedHeader: true,
    responsive: true,
    columnDefs: [
      { targets: [0, 1, 2, 4, 5], orderable: true, searchable: true },
      {
        targets: 3,
        render: function (data, type) {
          if (type === "filter" || type === "sort") {
            const div = document.createElement("div");
            div.innerHTML = data;
            const val = div.querySelector(".rating-value");
            return val ? val.textContent.trim() : "0";
          }
          return data;
        },
      },
      { targets: 6, visible: false, searchable: true },
    ],
    order: [[0, "asc"]],
    initComplete: function () {
      var api = this.api();

      // Set font size for the table
      $(this).css("font-size", "14px");
      $("#loading-spinner").hide();
      $("#allBooks").show();

      // Restore any saved text filters (row 2)
      $("#allBooks thead tr:eq(1) th input").each(function (colIndex) {
        if (this.value) api.column(colIndex).search(this.value);
      });

      // Restore status dropdown (filter against hidden Status Sort = col 5)
      const status = $("#filter-status").val();
      if (status) api.column(6).search("(^" + status + "$)", true, false);

      api.draw();

      // ---- Helpers ----
      function computeResponsiveVisibility(dt) {
        // Build an array like responsive-resize gives us: [true/false per column]
        var vis = [];
        dt.columns().every(function (i) {
          var hidden = false;

          // Prefer checking a body cell for dtr-hidden
          var $cells = dt.column(i).nodes().to$();
          if ($cells.length) {
            hidden = $cells.eq(0).hasClass("dtr-hidden");
          } else {
            // Fallbacks when there are 0 rows
            var $th = $(dt.column(i).header());
            hidden =
              $th.hasClass("dtr-hidden") || $th.css("display") === "none";
          }

          vis[i] = !hidden;
        });
        return vis;
      }

      function syncFilterHeaders(dt, visibilityArray) {
        // Apply to ALL theads (original + FixedHeader clone)
        $(dt.table().container())
          .find("thead")
          .each(function () {
            var $thead = $(this);
            visibilityArray.forEach(function (visible, i) {
              $thead.find("tr:eq(1) th").eq(i).toggle(visible);
            });
          });
      }

      // Force Responsive to calculate, then sync once on load
      setTimeout(function () {
        api.columns.adjust();
        if (api.responsive && api.responsive.recalc) {
          api.responsive.recalc();
        }
        var vis = computeResponsiveVisibility(api);
        syncFilterHeaders(api, vis);
      }, 0);

      // Also run once more after FixedHeader finishes cloning (next tick)
      setTimeout(function () {
        var vis = computeResponsiveVisibility(api);
        syncFilterHeaders(api, vis);
      }, 50);

      // Keep things in sync on later responsive changes
      api.on("responsive-resize", function (e, dt, columns) {
        syncFilterHeaders(dt, columns);
      });
    },
  });

  // Live text filters (Title/Author/Date/Rating)
  $("#allBooks thead tr:eq(1) th input").on("keyup change clear", function () {
    var colIdx = $(this).parent().index();
    allBooksTable.column(colIdx).search(this.value).draw();
  });

  // Rating filter (matches beginning of numeric value)
  $("#rating-status").on("input", function () {
    const rating = this.value.trim();
    if (rating === "") {
      allBooksTable.column(3).search("").draw();
    } else {
      allBooksTable
        .column(3)
        .search("^" + rating, true, false)
        .draw();
    }
  });

  // Format filter (matches beginning of numeric value)
  $("#format-status").on("input", function () {
    const format = this.value.trim();
    if (format === "") {
      allBooksTable.column(4).search("").draw();
    } else {
      allBooksTable
        .column(4)
        .search("^" + format, true, false)
        .draw();
    }
  });

  // Status dropdown changes filter is applied to hidden col 6
  $("#filter-status").on("change", function () {
    if (this.value === "") {
      allBooksTable.column(6).search("").draw();
    } else {
      allBooksTable
        .column(6)
        .search("(^" + this.value + "$)", true, false)
        .draw();
    }
  });

  // Handle the search form submission
  const oblTable = $("#search-table").DataTable();

  $("#book-search-form").on("submit", function (e) {
    e.preventDefault();
    const query = $("#book-title").val();

    $.ajax({
      url: `https://openlibrary.org/search.json`,
      method: "GET",
      data: { title: query },
      success: function (data) {
        if (data.numFound > 0) {
          oblTable.clear();
          data.docs.forEach((book) => {
            const title = book.title || "Unknown Title";
            const author = book.author_name
              ? book.author_name.join(", ")
              : "Unknown Author";
            const link = `https://openlibrary.org${book.key}`;
            oblTable.row.add([
              `<a href="/getBook?url=${encodeURIComponent(link)}">${title}</a>`,
              author,
              `<a href="${link}" target="_blank">Link</a>`,
            ]);
          });
          oblTable.draw();
        } else {
          alert("No results found.");
        }
      },
      error: function () {
        alert("An error occurred while fetching data.");
      },
    });
  });

  // Initialize Select2 for the book list
  $("#bookList").select2({
    placeholder: "Select lists",
    theme: "bootstrap-5",
    minimumResultsForSearch: Infinity,
    allowClear: true,
    width: "100%",
  });

  $(
    "#select2-bookList-container .select2-search.select2-search--inline"
  ).hide();

  // Hide the search field when opening or closing the select2 dropdown
  $("#bookList").on("select2:opening select2:closing", function () {
    var $searchWrapper = $(this).parent().find(".select2-search--inline");
    $searchWrapper.hide();
  });

  // Handle the book list selection
  $("#bookList").on("select2:select", function (e) {
    // get the book ID from the hidden input
    const bookId = $("input[name='bookId']").val();
    // get the selected list ID
    const listId = e.params.data.id;

    // Send the selected list ID and book ID to the server
    $.ajax({
      url: "/listChange",
      type: "POST",
      data: { bookId: bookId, listId: listId, action: "add" },
      success: function (response) {
        //console.log("Book added to list successfully:", response);
      },
      error: function (xhr, status, error) {
        // Optionally handle errors here
        //console.error("Error adding book to list:", error);
      },
    });
    //    console.log("Selected:", e.params.data);
  });

  // Handle the book list unselection
  $("#bookList").on("select2:unselect", function (e) {
    // get the book ID from the hidden input
    const bookId = $("input[name='bookId']").val();
    // get the selected list ID
    const listId = e.params.data.id;

    // Send the selected list ID and book ID to the server
    $.ajax({
      url: "/listChange",
      type: "POST",
      data: { bookId: bookId, listId: listId, action: "remove" },
      success: function (response) {
        //console.log("Book removed from list successfully:", response);
      },
      error: function (xhr, status, error) {
        //console.error("Error removing book from list:", error);
      },
    });
    //    console.log("Unselected:", e.params.data);
  });

  $("#listSelectHome").on("change", function () {
    const selectedId = $(this).val();

    if (selectedId) {
      window.location.href = "/?id=" + encodeURIComponent(selectedId);
    }
  });

  $("#listSelect").on("change", function () {
    const selectedId = $(this).val();

    if (selectedId) {
      window.location.href = "lists/?id=" + encodeURIComponent(selectedId);
    }
  });

  // Update star rating when the rating select changes
  // Override renderer to support 0.5 increments (0.5 to max)
  renderStars = function (rating, max) {
    const m = parseInt(max, 10) || 5;
    const r = Math.max(
      0,
      Math.min(m, Math.round((parseFloat(rating) || 0) * 2) / 2)
    );
    const pct = (r / m) * 100;
    const stars = "★".repeat(m);
    return `
      <span style="position:relative;display:inline-block;line-height:1;font-size:1.2rem;">
        <span aria-hidden="true" style="color:#ccc;">${stars}</span>
        <span aria-hidden="true" style="color:#f5c518;position:absolute;left:0;top:0;width:${pct}%;overflow:hidden;white-space:nowrap;">${stars}</span>
        <span class="visually-hidden">Rating ${r} out of ${m}</span>
      </span>
    `;
  };

  function renderStars(rating, max) {
    const r = Math.max(0, Math.min(max, parseInt(rating, 10) || 0));
    const filled = "★".repeat(r);
    const empty = "☆".repeat(max - r);
    return `<span aria-hidden="true" style="font-size:1.2rem;color:#f5c518;">${filled}</span><span aria-hidden="true" style="font-size:1.2rem;color:#ccc;">${empty}</span><span class="visually-hidden">Rating ${r} out of ${max}</span>`;
  }

  $(document).on("change", "#ratingSelect, .ratingSelect", function () {
    const $select = $(this);
    const value = $select.val();
    const max = parseInt($select.data("maxStars"), 10) || 5;

    let $target;
    const targetSelector = $select.data("starsTarget");
    if (targetSelector) {
      $target = $(targetSelector);
    } else {
      $target = $select.next(".js-star-output");
      if ($target.length === 0) {
        $target = $('<div class="js-star-output mt-1"></div>');
        $select.after($target);
      }
    }
    $target.html(renderStars(value, max));

    // Send the selected book ID and rating to the server
    // get the book ID from the hidden input
    const bookId = $("#ratingSelect").attr("name") || "";

    $.ajax({
      url: "/ratingChange",
      type: "POST",
      data: { name: bookId, value: value },
      success: function (response) {
        //console.log("Book removed from list successfully:", response);
      },
      error: function (xhr, status, error) {
        //console.error("Error removing book from list:", error);
      },
    });
  });

  // Initialize star display for existing selects on page load
  $("#ratingSelect, .ratingSelect").each(function () {
    $(this).trigger("change");
  });

  // Handle the read status change button
  $(document).on("click", "#readButton", function () {
    const $button = $(this); // Store reference to the button
    const bookId = $button.data("bookshelf");
    const buttonText = $button.text().trim();
    console.log("Book ID:", bookId, "Button Text:", buttonText);

    if (buttonText === "Mark as Unread") {
      statusValue = 0;
    } else if (buttonText === "Mark as Reading") {
      statusValue = 1;
    } else if (buttonText === "Mark as Read") {
      statusValue = 2;
    } else {
      statusValue = 0;
    }

    // Update the status in the UI
    $.ajax({
      url: "/statusChange",
      type: "POST",
      data: { name: "status_" + bookId, value: statusValue },
      success: function (response) {
        if (buttonText === "Mark as Unread") {
          $button.text("Mark as Reading");
        } else if (buttonText === "Mark as Reading") {
          $button.text("Mark as Read");
        } else if (buttonText === "Mark as Read") {
          $button.text("Mark as Unread");
        } else {
          $button.text("Mark as Unread");
        }
        console.log("Update successful:", response);
      },
      error: function (xhr, status, error) {
        // Optionally handle errors here
        console.error("Update failed:", error);
      },
    });
  });

  // Handle the Save changes button
  $(document).on("click", "#saveButton", function () {
    const ev = arguments[0];
    if (ev && typeof ev.preventDefault === "function") ev.preventDefault();
    const $review = $("#reviewText");
    const bookId = $review.attr("name") || "";
    const reviewText = $review.val() || "";
    console.log("Book ID:", bookId, "Review Text:", reviewText);

    // Update the status in the UI
    $.ajax({
      url: "/reviewChange",
      type: "POST",
      data: { name: bookId, value: reviewText },
      success: function (response) {
        console.log("Update successful:", response);
        window.location.href = "/";
      },
      error: function (xhr, status, error) {
        // Optionally handle errors here
        console.error("Update failed:", error);
        window.location.href = "/";
      },
    });
  });
});

// handle the date change
$(document).on("blur", "#datetimePicker", function () {
  const $select = $(this);
  const name = $select.attr("name");
  const value = $select.val();

  const dateInput = document.getElementById("datetimePicker");
  const inputDate = new Date(dateInput.value);
  const currentDate = new Date();

  const errorMessage = document.getElementById("error-message");
  errorMessage.style.display = "none";

  if (dateInput.value && inputDate >= currentDate) {
    errorMessage.style.display = "block";
    dateInput.focus();
    return;
  }

  const bookId = $("input[name='bookId']").val();
  const dateValue = value || "";
  console.log("Book ID:", bookId, "Date Value:", dateValue);

  $.ajax({
    url: "/dateChange",
    type: "POST",
    data: { name: bookId, value: dateValue },
    success: function (response) {
      console.log("Update successful:", response);
    },
    error: function (xhr, status, error) {
      console.error("Update failed:", error);
      window.location.href = "/";
    },
  });
});

function changeList(selectElement) {
  const $select = $(selectElement);
  const name = $select.attr("name");
  const value = $select.val();
  $.ajax({
    url: "/listChange",
    type: "POST",
    data: { name: name, value: value },
    success: function (response) {
      // Optionally handle the response here
      console.log("Update successful:", response);
    },
    error: function (xhr, status, error) {
      // Optionally handle errors here
      console.error("Update failed:", error);
    },
  });
}

// handle the change of read status
function changeStatus(selectElement) {
  const $select = $(selectElement);
  const name = $select.attr("name");
  const value = $select.val();
  const $table = $select.closest("table");
  const idSuffix = name.split("_").pop();
  const readDetails = document.getElementsByClassName("readDetails");
  let statusText = "";
  if (value === "0") {
    statusText = "Not Read";
    if (readDetails) $(".readDetails").hide();
  } else if (value === "1") {
    statusText = "Reading";
    if (readDetails) $(".readDetails").hide();
  } else if (value === "2") {
    statusText = "Read";
    // Find the "Date Read" row and set today's date
    const today = new Date().toISOString().split("T")[0]; // yyyy-mm-dd
    $table.find(".readDetails").first().find("td").eq(1).text(today);
    if (readDetails) $(".readDetails").show();
  } else {
    statusText = value;
  }
  $("#statusSort_" + idSuffix).text(statusText);

  // Update the status in the UI
  $.ajax({
    url: "/statusChange",
    type: "POST",
    data: { name: name, value: value },
    success: function (response) {
      // Optionally handle the response here
      console.log("Update successful:", response);
    },
    error: function (xhr, status, error) {
      // Optionally handle errors here
      console.error("Update failed:", error);
    },
  });
}

function updateURLFromFilters() {
  const params = new URLSearchParams();
  $("#allBooks thead input").each(function (i) {
    if (this.value) {
      const name = ["title", "author", "format"][i];
      params.set(name, this.value);
    }
  });
  history.replaceState(null, "", "?" + params.toString());
}

const video = document.getElementById("camera");
const resultDiv = document.getElementById("result");
const h2title = document.getElementById("h2title");

let quaggaReady = false; // Track if Quagga is initialized

// Ensure camera access before initializing Quagga
if (video) {
  navigator.mediaDevices
    .getUserMedia({ video: { facingMode: "environment" } })
    .then((stream) => {
      video.srcObject = stream;
      video.play();
      initQuagga();
    })
    .catch((error) => {
      console.error("Camera access error:", error);
      resultDiv.innerHTML = `Camera access error: ${error.message}`;
    });
}

// Function to initialize Quagga
function initQuagga() {
  if (quaggaReady) return; // Prevent multiple initializations

  Quagga.init(
    {
      inputStream: {
        name: "Live",
        type: "LiveStream",
        target: video,
        constraints: {
          width: { ideal: 1280 }, // Wider
          height: { ideal: 720 }, // Shorter
          facingMode: "environment",
          aspectRatio: { ideal: 16 / 9 }, // Ensures landscape mode
        },
      },
      locator: {
        patchSize: "medium", // Scan area size
        halfSample: true,
      },
      area: {
        // Set scan rectangle
        top: "20%", // Adjust vertical position
        right: "10%",
        left: "10%",
        bottom: "20%",
      },
      decoder: {
        readers: ["ean_reader"],
      },
    },
    (err) => {
      if (err) {
        console.error("Error initializing Quagga:", err);
        resultDiv.innerHTML = `Error initializing scanner: ${err.message}`;
        return;
      }
      console.log("Quagga initialized successfully");
      quaggaReady = true;
      Quagga.onDetected(onBarcodeDetected);
      startScanning();
    }
  );
}

// Function to start scanning
function startScanning() {
  if (quaggaReady) {
    video.style.display = "block";
    h2title.style.display = "block";
    resultDiv.innerHTML = "Scanning...";
    Quagga.start();
  } else {
    console.error("Quagga is not initialized yet.");
  }
}

// Function to stop scanning
function stopScanning() {
  video.style.display = "none";
  Quagga.stop();
  quaggaReady = false; // Mark as not ready so it can be restarted
}

// Handle barcode detection
function onBarcodeDetected(data) {
  if (data && data.codeResult && data.codeResult.code) {
    stopScanning();
    const isbn = data.codeResult.code;
    console.log("Detected ISBN:", isbn);
    resultDiv.innerHTML = `Scanned ISBN: ${isbn}`;
    fetchBookDetails(isbn);
  } else {
    console.warn("Invalid barcode data detected:", data);
  }
}

// Send book details to the server
if (document.getElementById("isbn-form")) {
  document.getElementById("isbn-form").addEventListener("submit", function (e) {
    e.preventDefault();
    document.getElementById("isbn-form").style.display = "none";
    const isbn = document.querySelector("input[name='isbn']").value;
    fetchBookDetails(isbn);
  });
}

// This function sends the book details to a PHP script for processing
function recordDetails(call) {
  const title = document.getElementById("book-title").textContent.trim();
  const authors = document.getElementById("authors").textContent.trim();
  const subject = document.getElementById("subject").textContent.trim();
  const isbn = document.getElementById("isbn").textContent.trim();
  const url = document
    .querySelector("button[onclick]")
    .getAttribute("onclick")
    .match(/window\.open\('(.*?)'/)[1];

  const data = { title, authors, subject, url, isbn };
  console.log("Recording details:", "/record" + call);
  fetch("/record" + call, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(data),
  })
    .then((response) => response.json())
    .then((result) => {
      if (result.success) {
        window.location.href = "/";
      } else {
        resultDiv.innerHTML = `Error recording details: ${result.error}`;
      }
    })
    .catch((error) => {
      resultDiv.innerHTML = `Error: ${error.message}`;
    });
}

// Function to fetch book details using ISBN
function fetchBookDetails(isbn) {
  console.log("Fetching book details for ISBN:", isbn);
  fetch(`/fetch-book?isbn=${isbn}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        resultDiv.innerHTML = `Error: ${data.error} - ${isbn}`;
      } else {
        resultDiv.innerHTML = `
                <strong>Book Details</strong>
                <table class="table table-striped">
                <tbody>
                <tr>
                    <td><strong>Title</strong></td><td id="book-title">${
                      data.title
                    }</td>
                </tr>
                <tr>
                    <td><strong>Author(s)</strong></td><td><span  id="authors">${data.authors.join(
                      ", "
                    )}</span></td>
                </tr>
                <tr>
                    <td><strong>Genre</strong></td><td><span id="subject">${
                      data.subject
                    }</span></td>
                </tr>
                <tr>
                    <td><strong>Publisher</strong></td><td><span>${
                      data.publisher
                    }</span></td>
                </tr>
                <tr>
                    <td><strong>Published Date</strong></td><td><span>${
                      data.publish_date
                    }</span></td>
                </tr>
                <tr>
                    <td><strong>ISBN</strong></td><td><span id="isbn">${
                      data.isbn
                    }</span></td>
                </tr>
                <tr>
                    <td><strong>Open Library Link</strong></td><td><button class="btn btn-primary" onclick="window.open('${
                      data.url
                    }', '_blank')" style="display: block;">Open link</button></td>
                </tr>
                </tbody>
                </table>
                <button id="read-button" class="btn btn-primary">Add</button>
            `;
        h2title.style.display = "none";
        // Now attach the listener
        const readButton = document.getElementById("read-button");
        if (readButton) {
          readButton.addEventListener("click", () => {
            console.log("Read button clicked");
            recordDetails("Csv");
          });
        }
      }
    })
    .catch((error) => {
      resultDiv.innerHTML = `Error fetching book details: ${error.message}`;
    });
}

// custom.js

(() => {
  "use strict";

  // Get the stored theme, if any
  const storedTheme = localStorage.getItem("theme");

  // Figure out what theme we should use
  const getPreferredTheme = () => {
    if (storedTheme) {
      return storedTheme;
    }

    return window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  };

  // Actually apply the theme to <html>
  const setTheme = function (theme) {
    if (theme === "auto") {
      document.documentElement.setAttribute(
        "data-bs-theme",
        window.matchMedia("(prefers-color-scheme: dark)").matches
          ? "dark"
          : "light"
      );
    } else {
      document.documentElement.setAttribute("data-bs-theme", theme);
    }
  };

  setTheme(getPreferredTheme());

  // Re-apply if system theme changes and we’re in auto
  window
    .matchMedia("(prefers-color-scheme: dark)")
    .addEventListener("change", () => {
      if (localStorage.getItem("theme") === "auto") {
        setTheme("auto");
      }
    });

  // When the page is fully loaded, wire up the dropdown buttons
  window.addEventListener("DOMContentLoaded", () => {
    const themeSwitcher = document.querySelector("#bd-theme");

    if (!themeSwitcher) {
      return;
    }

    // Update the dropdown UI (checkmark + icon in button)
    const showActiveTheme = (theme) => {
      const activeThemeIcon = document.querySelector(".theme-icon-active use");
      const btnToActivate = document.querySelector(
        `[data-bs-theme-value="${theme}"]`
      );
      const svgOfActiveBtn = btnToActivate
        .querySelector("svg use")
        .getAttribute("href");

      // Remove .active and aria-pressed
      document.querySelectorAll("[data-bs-theme-value]").forEach((el) => {
        el.classList.remove("active");
        el.setAttribute("aria-pressed", "false");
      });

      // Activate the chosen button
      btnToActivate.classList.add("active");
      btnToActivate.setAttribute("aria-pressed", "true");

      // Swap the icon in the toggle button
      activeThemeIcon.setAttribute("href", svgOfActiveBtn);
    };

    // Initial state
    showActiveTheme(getPreferredTheme());

    // Handle clicks
    document.querySelectorAll("[data-bs-theme-value]").forEach((toggle) => {
      toggle.addEventListener("click", () => {
        const theme = toggle.getAttribute("data-bs-theme-value");
        localStorage.setItem("theme", theme);
        setTheme(theme);
        showActiveTheme(theme);
      });
    });
  });
})();
