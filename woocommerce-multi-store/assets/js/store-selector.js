jQuery(function($) {
    'use strict';

    const storeSelector = {
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initMap();
        },

        cacheElements: function() {
            this.$container = $('.wc-store-selector-container');
            this.$selector = $('.wc-store-selector');
            this.$dropdown = $('.wc-store-selector-dropdown');
            this.$toggle = $('.wc-store-selector-toggle');
            this.$useLocation = $('.wc-store-use-location');
            this.$toggleMap = $('.wc-store-toggle-map');
            this.$map = $('.wc-store-selector-map');
            this.$storeList = $('.wc-store-selector-list');
            this.$storeItems = $('.wc-store-item');
            this.nonce = wc_store_selector_params.nonce || '';
        },

        bindEvents: function() {
            this.$toggle.on('click', this.toggleDropdown.bind(this));
            this.$useLocation.on('click', this.useLocation.bind(this));
            this.$toggleMap.on('click', this.toggleMap.bind(this));
            this.$storeItems.on('click', '.wc-store-select-button', this.selectStore.bind(this));
            $(document).on('click', this.closeOnClickOutside.bind(this));
        },

        toggleDropdown: function(e) {
            e.stopPropagation();
            this.$dropdown.toggleClass('active');
        },

        closeDropdown: function() {
            this.$dropdown.removeClass('active');
        },

        closeOnClickOutside: function(e) {
            if (!$(e.target).closest('.wc-store-selector').length) {
                this.closeDropdown();
            }
        },

        useLocation: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            $button.addClass('loading').prop('disabled', true);

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => this.handleGeolocationSuccess(position, $button),
                    (error) => this.handleGeolocationError(error, $button)
                );
            } else {
                this.showError(wc_store_selector_params.i18n.geolocation_not_supported);
                $button.removeClass('loading').prop('disabled', false);
            }
        },

        handleGeolocationSuccess: function(position, $button) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            // Store user location in cookies
            document.cookie = `wc_user_lat=${lat}; path=/`;
            document.cookie = `wc_user_lng=${lng}; path=/`;

            // Find nearest store
            let nearestStore = null;
            let minDistance = Infinity;

            this.$storeItems.each(function() {
                const storeId = $(this).data('store-id');
                const distance = parseFloat($(this).find('.wc-store-distance').text().split(' ')[0]);
                
                if (distance < minDistance) {
                    minDistance = distance;
                    nearestStore = storeId;
                }
            });

            if (nearestStore) {
                this.selectStoreById(nearestStore);
            }

            $button.removeClass('loading').prop('disabled', false);
        },

        handleGeolocationError: function(error, $button) {
            let errorMessage = wc_store_selector_params.i18n.geolocation_error;
            
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage = wc_store_selector_params.i18n.geolocation_denied;
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage = wc_store_selector_params.i18n.geolocation_unavailable;
                    break;
                case error.TIMEOUT:
                    errorMessage = wc_store_selector_params.i18n.geolocation_timeout;
                    break;
            }

            this.showError(errorMessage);
            $button.removeClass('loading').prop('disabled', false);
        },

        toggleMap: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const isMapVisible = this.$map.is(':visible');

            if (isMapVisible) {
                this.$map.slideUp();
                $button.html(`<span class="dashicons dashicons-location-alt"></span> ${wc_store_selector_params.i18n.view_on_map}`);
            } else {
                this.$map.slideDown();
                this.displayStoresOnMap();
                $button.html(`<span class="dashicons dashicons-location-alt"></span> ${wc_store_selector_params.i18n.hide_map}`);
            }
        },

        initMap: function() {
            if (typeof google === 'undefined' || !wc_store_selector_params.current_store) {
                return;
            }

            const selectedStore = wc_store_selector_params.current_store;
            const mapOptions = {
                zoom: 12,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                styles: [{
                    featureType: 'poi',
                    stylers: [{ visibility: 'off' }]
                }]
            };

            this.map = new google.maps.Map(this.$map[0], mapOptions);
            this.bounds = new google.maps.LatLngBounds();
            this.markers = [];
        },

        displayStoresOnMap: function() {
            if (!this.map) {
                this.initMap();
            }

            // Clear existing markers
            this.markers.forEach(marker => marker.setMap(null));
            this.markers = [];
            this.bounds = new google.maps.LatLngBounds();

            this.$storeItems.each((index, item) => {
                const $item = $(item);
                const storeId = $item.data('store-id');
                const storeName = $item.find('.wc-store-name').text();
                const storeAddress = $item.find('.wc-store-address').text();
                const lat = parseFloat($item.data('lat'));
                const lng = parseFloat($item.data('lng'));

                if (isNaN(lat) || isNaN(lng)) {
                    return;
                }

                const latLng = new google.maps.LatLng(lat, lng);
                this.bounds.extend(latLng);

                const marker = new google.maps.Marker({
                    position: latLng,
                    map: this.map,
                    title: storeName,
                    icon: {
                        url: wc_store_selector_params.marker_icon || '',
                        scaledSize: new google.maps.Size(30, 30)
                    }
                });

                this.markers.push(marker);

                const infoWindow = new google.maps.InfoWindow({
                    content: `<div class="wc-store-map-info">
                                <h4>${storeName}</h4>
                                <p>${storeAddress}</p>
                                <button class="button select-store-map" data-store-id="${storeId}">
                                    ${wc_store_selector_params.i18n.select}
                                </button>
                              </div>`
                });

                marker.addListener('click', () => {
                    infoWindow.open(this.map, marker);
                });

                google.maps.event.addListenerOnce(infoWindow, 'domready', () => {
                    $('.select-store-map').on('click', () => {
                        this.selectStoreById(storeId);
                        infoWindow.close();
                    });
                });
            });

            if (this.markers.length > 0) {
                this.map.fitBounds(this.bounds);
                if (this.markers.length === 1) {
                    this.map.setZoom(14);
                }
            }
        },

        selectStore: function(e) {
            e.preventDefault();
            const storeId = $(e.currentTarget).closest('.wc-store-item').data('store-id');
            this.selectStoreById(storeId);
        },

        selectStoreById: function(storeId) {
            const $button = $(`.wc-store-item[data-store-id="${storeId}"] .wc-store-select-button`);
            $button.addClass('loading').prop('disabled', true);

            $.ajax({
                url: wc_store_selector_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_set_selected_store',
                    store_id: storeId,
                    nonce: this.nonce
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        this.showError(response.data);
                    }
                },
                error: (xhr) => {
                    this.showError(xhr.responseJSON.data);
                },
                complete: () => {
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        },

        showError: function(message) {
            // Implement error display logic
            console.error(message);
        }
    };

    storeSelector.init();
});