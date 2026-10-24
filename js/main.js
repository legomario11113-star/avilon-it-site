/* =========================================================
   Avilon.IT — общие скрипты сайта
   Переиспользуются на всех страницах. Каждый блок ищет свои
   элементы через data-атрибуты/классы и ничего не делает,
   если на странице их нет — можно спокойно подключать везде.
   ========================================================= */

(function () {
  'use strict';

  /* ---------- Мобильное меню ---------- */
  function initNavToggle() {
    var toggle = document.querySelector('[data-nav-toggle]');
    var menu = document.querySelector('[data-nav-menu]');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', function () {
      var isOpen = menu.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(isOpen));
    });

    menu.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        menu.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  /* ---------- Форма заявки ---------- */
  function initRequestForm() {
    var form = document.querySelector('[data-request-form]');
    if (!form) return;

    var successBox = document.querySelector('[data-form-success]');

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      // Демо-заглушка: реальной отправки пока нет — сюда позже
      // подключится запрос к бэкенду/CRM.
      form.reset();
      if (successBox) {
        successBox.classList.add('is-visible');
      }
    });
  }

  /* ---------- Плавный переход хедера при скролле ---------- */
  function initHeaderShadow() {
    var header = document.querySelector('[data-site-header]');
    if (!header) return;

    var onScroll = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  document.addEventListener('DOMContentLoaded', function () {
    initNavToggle();
    initRequestForm();
    initHeaderShadow();
  });
})();
