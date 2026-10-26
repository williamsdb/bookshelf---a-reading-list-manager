// Apply before paint; appearance is independent of the light/dark theme.
(function () {
  'use strict';
  var classic = false;
  try { classic = localStorage.getItem('bookshelf-appearance') === 'classic'; } catch (e) {}
  document.documentElement.setAttribute('data-appearance', classic ? 'classic' : 'modern');
  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('classic-appearance');
    if (!toggle) return;
    toggle.checked = classic;
    toggle.addEventListener('change', function () {
      var appearance = toggle.checked ? 'classic' : 'modern';
      document.documentElement.setAttribute('data-appearance', appearance);
      try { localStorage.setItem('bookshelf-appearance', appearance); } catch (e) {}
    });
  });
}());
