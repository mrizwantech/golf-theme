document.addEventListener('DOMContentLoaded', function () {
    initHeroSlider();
    initCardMediaHover();
    initPagePrefetch();
});

function initPagePrefetch() {
    var prefetchedUrls = new Set();

    function prefetchLink(link) {
        if (!link || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }

        var destination;
        try {
            destination = new URL(link.href, window.location.href);
        } catch (e) {
            return;
        }

        if (destination.origin !== window.location.origin
            || destination.pathname === window.location.pathname
            || destination.search
            || destination.hash
            || destination.pathname.indexOf('/wp-admin/') === 0
            || destination.pathname.indexOf('/wp-login.php') !== -1
            || destination.pathname.indexOf('/admin-post.php') !== -1) {
            return;
        }

        var url = destination.href;
        if (prefetchedUrls.has(url)) {
            return;
        }
        prefetchedUrls.add(url);

        var hint = document.createElement('link');
        hint.rel = 'prefetch';
        hint.href = url;
        document.head.appendChild(hint);
    }

    document.addEventListener('pointerover', function (event) {
        if (event.pointerType !== 'mouse') {
            return;
        }
        prefetchLink(event.target.closest('a[href]'));
    }, { passive: true });

    document.addEventListener('focusin', function (event) {
        prefetchLink(event.target.closest('a[href]'));
    });
}

function initHeroSlider() {
    var slider = document.querySelector('.hero-slider');

    if (!slider) {
        return;
    }

    var slides = Array.prototype.slice.call(slider.querySelectorAll('.hero-slide'));
    var prevButton = slider.querySelector('.slider-prev');
    var nextButton = slider.querySelector('.slider-next');
    var dotsContainer = slider.querySelector('.slider-dots');

    if (slides.length <= 1 || !prevButton || !nextButton || !dotsContainer) {
        return;
    }

    var currentIndex = 0;
    var autoplayMs = 5000;
    var timerId = null;

    function loadSlideBackground(slide) {
        var imageUrl = slide.getAttribute('data-background-image');
        if (imageUrl) {
            slide.style.backgroundImage = 'url("' + imageUrl.replace(/"/g, '\\"') + '")';
            slide.removeAttribute('data-background-image');
        }
    }

    function setActiveSlide(index) {
        loadSlideBackground(slides[index]);
        slides.forEach(function (slide, i) {
            slide.classList.toggle('active', i === index);
        });

        Array.prototype.slice.call(dotsContainer.querySelectorAll('.dot')).forEach(function (dot, i) {
            dot.classList.toggle('active', i === index);
            dot.setAttribute('aria-selected', i === index ? 'true' : 'false');
        });

        currentIndex = index;
    }

    function goToNext() {
        var nextIndex = (currentIndex + 1) % slides.length;
        setActiveSlide(nextIndex);
    }

    function goToPrev() {
        var prevIndex = (currentIndex - 1 + slides.length) % slides.length;
        setActiveSlide(prevIndex);
    }

    function restartAutoplay() {
        if (timerId) {
            window.clearInterval(timerId);
        }
        timerId = window.setInterval(goToNext, autoplayMs);
    }

    slides.forEach(function (_, index) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'dot' + (index === 0 ? ' active' : '');
        dot.setAttribute('aria-label', 'Go to slide ' + (index + 1));
        dot.setAttribute('role', 'tab');
        dot.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
        dot.addEventListener('click', function () {
            setActiveSlide(index);
            restartAutoplay();
        });
        dotsContainer.appendChild(dot);
    });

    nextButton.addEventListener('click', function () {
        goToNext();
        restartAutoplay();
    });

    prevButton.addEventListener('click', function () {
        goToPrev();
        restartAutoplay();
    });

    slider.addEventListener('mouseenter', function () {
        if (timerId) {
            window.clearInterval(timerId);
        }
    });

    slider.addEventListener('mouseleave', function () {
        restartAutoplay();
    });

    restartAutoplay();
}

function initCardMediaHover() {
    var cards = document.querySelectorAll('.card.has-media');
    if (!cards.length) {
        return;
    }

    cards.forEach(function (card) {
        var video = card.querySelector('video');
        var gifImg = card.querySelector('img.hover-gif');

        if (video) {
            video.muted = true;
            video.defaultMuted = true;
            video.setAttribute('muted', '');
            video.setAttribute('playsinline', '');
            video.pause();

            function playVideo() {
                video.muted = true;
                if (!video.getAttribute('src') && video.dataset.src) {
                    video.setAttribute('src', video.dataset.src);
                    video.load();
                }
                var promise = video.play();
                if (promise !== undefined && typeof promise.catch === 'function') {
                    promise.catch(function (err) {
                        console.warn('Video play interrupted or blocked:', err);
                    });
                }
            }

            function pauseVideo() {
                video.pause();
            }

            card.addEventListener('mouseenter', playVideo);
            card.addEventListener('mouseleave', pauseVideo);
            card.addEventListener('focusin', playVideo);
            card.addEventListener('focusout', pauseVideo);

            // Click to toggle play / pause explicitly
            card.addEventListener('click', function (e) {
                // If user clicks a link/button inside card, let default action happen
                if (e.target.closest('a, button')) {
                    return;
                }
                if (video.paused) {
                    playVideo();
                } else {
                    pauseVideo();
                }
            });

            card.addEventListener('touchstart', function (e) {
                if (e.target.closest('a, button')) {
                    return;
                }
                if (video.paused) {
                    playVideo();
                } else {
                    pauseVideo();
                }
            }, { passive: true });
        }

        if (gifImg) {
            function setupGifFreeze() {
                if (card.querySelector('.gif-freeze-frame')) {
                    return;
                }
                var canvas = document.createElement('canvas');
                canvas.className = 'gif-freeze-frame';
                canvas.width = gifImg.naturalWidth || gifImg.clientWidth || 640;
                canvas.height = gifImg.naturalHeight || gifImg.clientHeight || 360;
                var ctx = canvas.getContext('2d');
                if (ctx) {
                    try {
                        ctx.drawImage(gifImg, 0, 0, canvas.width, canvas.height);
                        gifImg.parentNode.insertBefore(canvas, gifImg.nextSibling);
                    } catch (e) { }
                }
            }

            if (gifImg.complete && gifImg.naturalWidth) {
                setupGifFreeze();
            } else {
                gifImg.addEventListener('load', setupGifFreeze);
            }

            function playGif() {
                var currentSrc = gifImg.src;
                if (currentSrc) {
                    gifImg.src = '';
                    gifImg.src = currentSrc;
                }
            }

            card.addEventListener('mouseenter', playGif);
            card.addEventListener('focusin', playGif);
            card.addEventListener('click', function (e) {
                if (e.target.closest('a, button')) {
                    return;
                }
                playGif();
            });
        }
    });
}
