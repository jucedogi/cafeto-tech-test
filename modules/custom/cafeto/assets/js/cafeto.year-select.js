"use strict";
(function ($) {
  $(function () {
    const yearSelect = $("#year-select");
    const carouselWrapper = $("#carousel-wrapper");
    yearSelect.change(function () {
      const selectedYear = yearSelect.val();
      if (!isNaN(selectedYear)) {
        carouselWrapper
            .html("<img alt='' src='/modules/custom/cafeto/assets/images/ajax-loader.gif' style='display: block; margin: 60px auto;'>")
            .load(`/cafeto/movies/${selectedYear}`);
      }
    });
  });
})(jQuery);
