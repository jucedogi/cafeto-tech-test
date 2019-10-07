"use strict";
(function ($) {
  $(function () {
    const carousel = $("#slick-carousel");
    carousel.slick({
      accessibility: true,
      adaptiveHeight: false,
      autoplay: false,
      autoplaySpeed: 3000,
      arrows: true,
      asNavFor: null,
      // appendArrows: $("#slick-carousel-nav"),
      appendDots: $("#slick-carousel-dots"),
      prevArrow: $("#slick-nav-prev"),
      nextArrow: $("#slick-nav-next"),
      centerMode: false,
      dots: false,
      dotsClass: "slick-dots",
      draggable: true,
      fade: true,
      infinite: true,
      mobileFirst: true,
      respondTo: 'min',
      swipe: true,
      lazyLoad: 'ondemand',
      slidesToShow: 1,
      slidesToScroll: 1
    });
  });
})(jQuery);
