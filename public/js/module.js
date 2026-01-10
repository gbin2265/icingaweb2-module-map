(function (Icinga) {

    function colorMarker(worstState, icon) {
        return L.AwesomeMarkers.icon({
            icon: icon,
            markerColor: state2color(worstState),
            className: 'awesome-marker'
        });
    }

    function state2color(state) {
        switch (parseInt(state)) {
            case 0:
                return "green";
            case 1:
                return "orange";
            case 2:
                return "red";
            case 3:
                return "purple";
            case 10:
                return "lightgreen";
            case 11:
                return "beige";
            case 12:
                return "lightred";
            case 13:
                return "pink";
            default:
                return "blue";
        }
    }

    function isFilterParameter(parameter) {
        return (parameter.charAt(0) === '(' || parameter.match('^[_]{0,1}(host|service)') || parameter.match('^(object|state)Type') || parameter.match('^problems'));
    }

    function getParameters(id) {
        var params = decodeURIComponent($('#map-' + id).closest('.module-map').data('icingaUrl')).split('&');

        if (params.length > 0) {
            params[0] = params[0].replace(/^.*\?/, '');
        }

        return params;
    }

    function unique(list) {
        var result = [];
        $.each(list, function (i, e) {
            if ($.inArray(e, result) === -1) result.push(e);
        });
        return result;
    }

    function filterParams(id, extra) {
        var sURLVariables = getParameters(id);
        var params = [];

        if (extra !== undefined) {
            sURLVariables = $.merge(extra.split('&'), sURLVariables);
            sURLVariables = unique(sURLVariables);
        }

        for (var i = 0; i < sURLVariables.length; i++) {
            if (isFilterParameter(sURLVariables[i])) {
                params.push(sURLVariables[i]);
            }
        }

        return params.join("&");
    }

    function showHost(hostname) {
        if (cache[id].hostMarkers[hostname]) {
            var el = cache[id].hostMarkers[hostname];
            cache[id].markers.zoomToShowLayer(el, function () {
                el.openPopup();
            });
        }
    }

    function showDefaultView() {
        if (map_default_lat !== null && map_default_long !== null) {
            if (map_default_zoom !== null) {
                cache[id].map.setView([map_default_lat, map_default_long], map_default_zoom);
            } else {
                cache[id].map.setView([map_default_lat, map_default_long]);
            }
        } else {
            cache[id].map.fitWorld();
        }
    }

    function toggleFullscreen() {
        icinga.ui.toggleFullscreen();
        cache[id].map.invalidateSize();
        cache[id].fullscreen = !cache[id].fullscreen;
        if (cache[id].fullscreen) {
            $('.controls').hide();
        } else {
            $('.controls').show();
        }
    }

    function updateUrl(pkey, pvalue) {
        if (dashlet) {
            return;
        }

        var $target = $('.module-map');
        var $currentUrl = $target.data('icingaUrl');
        var basePath = $currentUrl.replace(/\?.*$/, '');
        var searchPath = $currentUrl.replace(/^.*\?/, '');

        var sURLVariables = (searchPath === basePath ? [] : searchPath.split('&'));

        var updated = false;
        for (var i = 0; i < sURLVariables.length; i++) {
            if (isFilterParameter(sURLVariables[i])) {
                continue;
            }

            var tmp = sURLVariables[i].split('=');
            if (tmp[0] === pkey) {
                sURLVariables[i] = tmp[0] + '=' + pvalue;
                updated = true;
                break;
            }
        }

        if (!updated) {
            sURLVariables.push(pkey + "=" + pvalue);
        }

        $target.data('icingaUrl', basePath + '?' + sURLVariables.join('&'));
        icinga.history.pushCurrentState();
    }

    function getWorstState(states) {
        var worstState = 0;
        var allPending = -1;
        var allUnknown = -1;
        var last = -1;

        if (states.length === 1) {
            return states[0];
        }

        for (var i = 0, len = states.length; i < len; i++) {
            var state = states[i];
            if (state < 3) {
                if (allPending === 1) {
                    allPending = 0;
                } else if (allUnknown === 1) {
                    allUnknown = 0;
                }
            }

            if (state > 2) {
                if (state === 99) {
                    if (allPending < 0 && last < 0) {
                        allPending = 1;
                    }
                    state = 0.25;
                }

                if (state === 3) {
                    if (allUnknown < 0 && last < 0) {
                        allUnknown = 1;
                    }
                    state = 0.5;
                }
            }

            if (state > worstState) {
                worstState = state;
            }

            last = state;
        }

        if (allPending === 1) {
            worstState = 99;
        }

        if (allUnknown === 1) {
            worstState = 3;
        }

        if (worstState === 0.25) {
            worstState = 99;
        } else if (worstState === 0.5) {
            worstState = 3;
        }

        return worstState;
    }

    function mapCenter(hostname) {
        if (cache[id].hostMarkers[hostname]) {
            cache[id].map.panTo(cache[id].hostMarkers[hostname].getLatLng());
        }
    }

    var cache = {};

    var Map = function (module) {
        this.module = module;
        this.initialize();
        this.timer;
    };

    Map.prototype = {

        initialize: function () {
            this.timer = {};
            this.module.on('rendered', this.onRenderedContainer);
            this.registerTimer();
        },

        registerTimer: function (id) {
            this.timer = this.module.icinga.timer.register(
                this.updateAllMapData,
                this,
                0
            );
            return this;
        },

        removeTimer: function (id) {
            this.module.icinga.timer.unregister(this.timer);
            return this;
        },

        onPopupOpen: function (evt) {
            $('.detail-link').on("click", function (ievt) {
                mapCenter(evt.popup._source.options.id);
                cache[id].map.invalidateSize();
            });
        },

        updateAllMapData: function () {
            var _this = this;

            if (cache.length === 0) {
                this.removeTimer(id);
                return this;
            }

            $.each(cache, function (id) {
                if (!$('#map-' + id).length) {
                    delete cache[id];
                } else {
                    _this.updateMapData({id: id});
                }
            });
        },

        updateMapData: function (parameters) {
            var id = parameters.id;
            var show_host = parameters.show_host;
            var $that = this;

            function removeOldMarkers(id, data) {
                $.each(cache[id].hostMarkers, function (identifier, d) {
                    if ((data['hosts'] && !data['hosts'][identifier]) && (data['services'] && !data['services'][identifier])) {
                        cache[id].markers.removeLayer(d);
                        delete cache[id].hostMarkers[identifier];
                    }
                });
            }

            function errorMessage(msg) {
                cache[id].map.spin(false);
                var $map = cache[id].map;
                $map.openModal({
                    content: "<p>Could not fetch data from API:</p><pre>" + msg + "</pre>",
                    onShow: function (evt) {
                        $that.removeTimer(id);
                    },
                    onHide: function (evt) {
                        $that.registerTimer(id);
                    }
                });
            }

            function processData(json) {
                if (json['message']) {
                    errorMessage(json['message']);
                    return;
                }
                removeOldMarkers(id, json);

                $.each(json, function (type, element) {
                    $.each(element, function (identifier, data) {
                        if (data.length < 1 || data['coordinates'] === "") {
                            console.log('found empty coordinates: ' + data);
                            return true;
                        }

                        var states = [];
                        var icon;
                        var worstState;
                        var worstStateHandled = 0;
                        var display_name = data['host_display_name'] ? data['host_display_name'] : identifier;
                        var marker_icon = 'host';

                        if (type === 'hosts') {
                            states.push(data['host_state'] === 1 ? 2 : data['host_state']);
                            if (data['hosts_total'] > 0) {
                                states.push(data['host_state_service']);
                            }
                        }

                        if (data['icon']) {
                            marker_icon = data['icon'];
                        }

                        worstState = getWorstState(states);

                        var hostLink = '/icingadb/host?name=' + data['host_name'];
                        var table = '<table class="icinga-module module-icingadb">';

                        var info = '<div id="layout"><div class="main">';
                        info += table;
                        info += '<tr>';
                        info += '<td><a data-hostname="' + data['host_name'] + '" data-base-target="_next" href="' + icinga.config.baseUrl + hostLink + '">';

                        var downack = '';
                        var downackhandled = '';

                        if (data['hosts_is_acknowledged'] > 0) {
                            downack = '<i class="icon fa-check fa"></i>';
                            downackhandled = 'handled';
                            marker_icon = 'check';
                            worstStateHandled = 10;
                        } else if (data['hosts_in_downtime'] > 0) {
                            downack = '<i class="icon fa-plug fa"></i>';
                            downackhandled = 'handled';
                            marker_icon = 'plug';
                            worstStateHandled = 10;
                        }

                        if (data['hosts_down_handled'] > 0) {
                            info += '<span class="state-ball ball-size-l state-down handled">' + downack + '</span>';
                        } else if (data['hosts_down_unhandled'] > 0) {
                            info += '<span class="state-ball ball-size-l state-down">' + downack + '</span>';
                        } else if (data['hosts_pending'] > 0) {
                            info += '<span class="state-ball ball-size-l state-pending ' + downackhandled + '">' + downack + '</span>';
                        } else {
                            info += '<span class="state-ball ball-size-l state-up"></span>';
                            if (worstState > 0) {
                                marker_icon = 'service';
                            }
                        }

                        info += '</a></td>';
                        info += '<td><a data-hostname="' + data['host_name'] + '" data-base-target="_next" href="' + icinga.config.baseUrl + hostLink + '">';
                        info += '<div class="item-layout"><b>' + data['host_display_name'] + '</b></div>';
                        info += '</a></td>';
                        info += '</tr>';
                        info += '</table>';

                        if (data['services_total'] > 0) {
                            info += table;
                            info += '<tr>';
                            info += '<td><a href="' + icinga.config.baseUrl + '/icingadb/services?host.name=' + data['host_name'] + '" data-base-target="_next"><div class="vertical-key-value" title="' + data['services_total'] + '"><span class="value">' + data['services_total'] + '</span><br /><span class="key">Services</span></div></a></td>';

                            if (data['services_critical_unhandled'] > 0) {
                                info += '<td><a href="' + icinga.config.baseUrl + '/icingadb/services?(service.state.soft_state=2&service.state.is_handled=n&service.state.is_reachable=y)&host.name=' + data['host_name'] + '" data-base-target="_next"><span class="state-badge state-critical">' + data['services_critical_unhandled'] + '</span></a></td>';
                            }
                            if (data['services_critical_handled'] > 0) {
                                info += '<td><a href="' + icinga.config.baseUrl + '/icingadb/services?(service.state.soft_state=2&(service.state.is_handled=y|service.state.is_reachable=n))&host.name=' + data['host_name'] + '" data-base-target="_next"><span class="state-badge state-critical handled">' + data['services_critical_handled'] + '</span></a></td>';
                            }
                            if (data['services_warning_unhandled'] > 0) {
                                info += '<td><a href="' + icinga.config.baseUrl + '/icingadb/services?(service.state.soft_state=1&service.state.is_handled=n&service.state.is_reachable=y)&host.name=' + data['host_name'] + '" data-base-target="_next"><span class="state-badge state-warning">' + data['services_warning_unhandled'] + '</span></a></td>';
                            }
                            if (data['services_warning_handled'] > 0) {
                                info += '<td><a href="' + icinga.config.baseUrl + '/icingadb/services?(service.state.soft_state=1&(service.state.is_handled=y|service.state.is_reachable=n))&host.name=' + data['host_name'] + '" data-base-target="_next"><span class="state-badge state-warning handled">' + data['services_warning_handled'] + '</span></a></td>';
                            }
                            if (data['services_unknown_unhandled'] > 0) {
                                info += '<td><a href="' + icinga.config.baseUrl + '/icingadb/services?(service.state.soft_state=3&service.state.is_handled=n&service.state.is_reachable=y)&host.name=' + data['host_name'] + '" data-base-target="_next"><span class="state-badge state-unknown">' + data['services_unknown_unhandled'] + '</span></a></td>';
                            }
                            if (data['services_unknown_handled'] > 0) {
                                info += '<td><a href="' + icinga.config.baseUrl + '/icingadb/services?(service.state.soft_state=3&(service.state.is_handled=y|service.state.is_reachable=n))&host.name=' + data['host_name'] + '" data-base-target="_next"><span class="state-badge state-unknown handled">' + data['services_unknown_handled'] + '</span></a></td>';
                            }
                            if (data['services_ok'] > 0) {
                                info += '<td><a href="' + icinga.config.baseUrl + '/icingadb/services?service.state.soft_state=0&host.name=' + data['host_name'] + '" data-base-target="_next"><span class="state-badge state-ok">' + data['services_ok'] + '</span></a></td>';
                            }
                            if (data['services_pending'] > 0) {
                                info += '<td><a href="' + icinga.config.baseUrl + '/icingadb/services?service.state.soft_state=99&host.name=' + data['host_name'] + '" data-base-target="_next"><span class="state-badge state-pending">' + data['services_pending'] + '</span></a></td>';
                            }

                            info += '</tr>';
                            info += '</table>';
                        }

                        info += '</div></div>';

                        icon = colorMarker(worstState + worstStateHandled, marker_icon);

                        var marker;

                        if (cache[id].hostMarkers[identifier]) {
                            marker = cache[id].hostMarkers[identifier];
                            marker.options.state = worstState;
                            marker.setIcon(icon);
                        } else {
                            marker = L.marker(data['coordinates'], {
                                icon: icon,
                                title: display_name,
                                riseOnHover: true,
                                id: identifier,
                                state: worstState
                            }).addTo(cache[id].markers);

                            cache[id].hostMarkers[identifier] = marker;
                            cache[id].hostData[identifier] = data;
                        }

                        marker.bindPopup(info);

                        if (popup_mouseover) {
                            marker.on('mouseover', function (e) {
                                this.openPopup();
                            });
                            marker.on('mouseout', function (e) {
                                // this.closePopup();
                            });
                        }
                    });
                });

                cache[id].markers.refreshClusters();
                cache[id].map.spin(false);
                cache[id].map.invalidateSize();

                if (show_host !== "") {
                    showHost(show_host);
                    show_host = "";
                }
            }

            var url = icinga.config.baseUrl + '/map/data/points?' + filterParams(id, cache[id].parameters);
            $.getJSON(url, processData)
                .fail(function (jqxhr, textStatus, error) {
                    errorMessage(error);
                });
        },

        onRenderedContainer: function (event) {
            var attrs = event.currentTarget.querySelector('.icinga-module.module-map > .content > #map-script').dataset;
            attrs = JSON.parse(attrs.mapAttrs);

            for (var key in attrs) {
                if (attrs.hasOwnProperty(key)) {
                    var value = attrs[key];
                    if (typeof value === 'object') {
                        for (var key2 in value) {
                            if (value.hasOwnProperty(key2)) {
                                if (typeof window[key] === 'undefined') {
                                    window[key] = {};
                                }
                                window[key][key2] = value[key2];
                            }
                        }
                    } else {
                        window[key] = value;
                    }
                }
            }

            cache[id] = {};
            cache[id].map = L.map('map-' + id, {
                zoomControl: false,
                worldCopyJump: true
            });

            if (typeof id === 'undefined') {
                return;
            }

            var osm = L.tileLayer(tile_url, {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                subdomains: ['a', 'b', 'c'],
                maxNativeZoom: map_max_native_zoom,
                maxZoom: map_max_zoom,
                minZoom: map_min_zoom
            });
            osm.addTo(cache[id].map);

            var options = {
                limit: 10,
                filter: function () {
                    return filterParams(id, cache[id].parameters);
                }
            };
            var control = L.Control.openCageSearch(options).addTo(cache[id].map);

            control.setMarker(function (el) {
                if (el['id'] && cache[id].hostMarkers[el.id]) {
                    showHost(el.id);
                } else {
                    var geocodeMarker = new L.Marker(el.center, {
                        icon: L.AwesomeMarkers.icon({
                            icon: 'globe',
                            markerColor: 'blue',
                            className: 'awesome-marker'
                        })
                    })
                        .bindPopup(el.name)
                        .addTo(cache[id].map)
                        .openPopup();

                    cache[id].map.setView(geocodeMarker.getLatLng(), map_max_zoom);

                    geocodeMarker.on('popupclose', function (evt) {
                        cache[id].map.removeLayer(evt.target);
                    });
                }
            });

            cache[id].markers = new L.MarkerClusterGroup({
                iconCreateFunction: function (cluster) {
                    var childCount = cluster.getChildCount();
                    var childProblem = 0;

                    var states = [];
                    $.each(cluster.getAllChildMarkers(), function (id, el) {
                        states.push(el.options.state);

                        if (el.options.state > 0) {
                            childProblem++;
                        }
                    });

                    var worstState = getWorstState(states);
                    var c = ' marker-cluster-' + worstState;
                    var clusterLabel = childProblem + '/' + childCount;

                    if (cluster_problem_count) {
                        clusterLabel = childProblem;
                    }

                    return new L.DivIcon({
                        html: '<div><span>' + clusterLabel + '</span></div>',
                        className: 'marker-cluster' + c,
                        iconSize: new L.Point(40, 40)
                    });
                },
                maxClusterRadius: function (zoom) {
                    return (zoom <= disable_cluster_at_zoom) ? 80 : 1;
                }
            });

            cache[id].hostMarkers = {};
            cache[id].hostData = {};
            cache[id].fullscreen = false;
            cache[id].parameters = url_parameters;

            showDefaultView();

            cache[id].map.on('popupopen', this.onPopupOpen);

            L.control.zoom({
                zoomInTitle: translation['btn-zoom-in'],
                zoomOutTitle: translation['btn-zoom-out']
            }).addTo(cache[id].map);

            if (!dashlet) {
                L.easyButton({
                    states: [{
                        icon: 'icon-dashboard',
                        title: translation['btn-dashboard'],
                        onClick: function (btn, map) {
                            var dashletUri = "map" + window.location.search;
                            var uri = icinga.config.baseUrl + "/" + "dashboard/new-dashlet?url=" + encodeURIComponent(dashletUri);
                            window.open(uri, "_self");
                        }
                    }]
                }).addTo(cache[id].map);

                L.easyButton({
                    states: [{
                        icon: 'icon-resize-full-alt',
                        title: translation['btn-fullscreen'],
                        onClick: function (btn, map) {
                            toggleFullscreen();
                        }
                    }]
                }).addTo(cache[id].map);

                L.easyButton({
                    states: [{
                        icon: 'icon-globe',
                        title: translation['btn-default'],
                        onClick: function (btn, map) {
                            showDefaultView();
                        }
                    }]
                }).addTo(cache[id].map);

                L.control.locate({
                    icon: 'icon-pin',
                    strings: {title: translation['btn-locate']}
                }).addTo(cache[id].map);

                cache[id].map.on('map-container-resize', function () {
                    cache[id].map.invalidateSize();
                });

                cache[id].map.on('moveend', function (e) {
                    var center = cache[id].map.getCenter();
                    updateUrl('default_lat', center.lat);
                    updateUrl('default_long', center.lng);
                });

                cache[id].map.on('zoomend', function (e) {
                    var zoomLevel = cache[id].map.getZoom();
                    updateUrl('default_zoom', zoomLevel);
                });

                cache[id].map.on('click', function (e) {
                    if (e.originalEvent.ctrlKey) {
                        var coord = 'vars.geolocation = "'
                            + e.latlng.lat.toFixed(6)
                            + ','
                            + e.latlng.lng.toFixed(6)
                            + '"';

                        var popup = "<h1>Location selected</h1>"
                            + "<p>To use this location with your host(s) or service(s), just add the following config to your object definition:</p>"
                            + "<pre>" + coord + "</pre>";

                        var marker = L.marker(e.latlng, {icon: colorMarker(99, 'globe')});
                        marker.bindPopup(popup);
                        marker.addTo(cache[id].markers);

                        marker.on('popupclose', function (evt) {
                            cache[id].markers.removeLayer(evt.target);
                        });

                        cache[id].markers.zoomToShowLayer(marker, function () {
                            marker.openPopup();
                        });
                    }
                });
            }

            cache[id].markers.addTo(cache[id].map);

            cache[id].map.spin(true);
            this.updateMapData({id: id, show_host: map_show_host});
        }
    };

    Icinga.availableModules.map = Map;

}(Icinga));
