$(function() {

    console.log('start of script');

    /**
     * STICKY HEADER
     */
    const nav = document.querySelector('nav .nav-wrap');

    window.addEventListener('scroll', () => {
        if (window.scrollY > 0) {
            nav.classList.add('sticky');
        } else {
            nav.classList.remove('sticky');
        }
    });


    /**
     * PRODUCTS CAROUSEL
     */
    const productsSwiper = new Swiper('.product-carousel', {
        slidesPerView: 1,
        spaceBetween: 43,
        loop: false,
        pagination: {
            el: '.product-pagination'
        },
        breakpoints: {
            768: {
                slidesPerView: 1.2
            }
        }
    });


    /**
     * REVIEWS CAROUSEL
     */
    const reviewsSwiper = new Swiper('.review-carousel', {
        slidesPerView: 1,
        spaceBetween: 43,
        loop: false,
        breakpoints: {
            568: {
                slidesPerView: 1.5
            },
            992: {
                slidesPerView: 2
            },
            1200: {
                slidesPerView: 2.5
            },
            1400: {
                slidesPerView: 3.2
            }
        }
    });


    /**
     * WE OFFER CAROUSEL
     */
    const weOfferSwiper = new Swiper('.we-offer-carousel', {
        slidesPerView: 1,
        spaceBetween: 30,
        loop: false,
        breakpoints: {
            680: {
                slidesPerView: 1.5
            }
        }
    });


    /**
     * SOCIAL CAROUSEL
     */
    const socialSwiper = new Swiper('.social-carousel', {
        slidesPerView: 1.5,
        spaceBetween: 20,
        loop: false,
        breakpoints: {
            680: {
                slidesPerView: 2
            },
            992: {
                slidesPerView: 2.5
            },
            1100: {
                slidesPerView: 3
            }
        }
    });
});