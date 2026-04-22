/* ══════════════════════════════════════════════════════════════════
   TravelScope — app.js
   ══════════════════════════════════════════════════════════════════ */

// ─── DOM Elements ─────────────────────────────────────────────
const searchInput    = document.getElementById('search-input');
const searchBtn      = document.getElementById('search-btn');
const resultsSection = document.getElementById('results-section');
const errorToast     = document.getElementById('error-toast');
const toastMessage   = document.getElementById('toast-message');
const navbar         = document.getElementById('navbar');

// ─── State ────────────────────────────────────────────────────
let currentBudget = 'budget';
let currentData   = null;

// ─── Currency Conversion (dynamic, updated per search) ───────
let currentExchangeRate = 84.5; // fallback, overwritten per search
let currentCurrency = 'USD';    // destination native currency

function toINR(amount) {
    return Math.round(amount * currentExchangeRate);
}
function formatINR(amount) {
    return '₹' + amount.toLocaleString('en-IN');
}

// ═══════════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    createParticles();
    bindEvents();
});


// ─── Background Particles ────────────────────────────────────
function createParticles() {
    const container = document.getElementById('particles');
    const colors = ['#6366f1', '#a855f7', '#ec4899', '#06b6d4', '#10b981'];
    
    for (let i = 0; i < 30; i++) {
        const particle = document.createElement('div');
        particle.classList.add('particle');
        const size = Math.random() * 6 + 2;
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.background = colors[Math.floor(Math.random() * colors.length)];
        particle.style.animationDuration = (Math.random() * 20 + 15) + 's';
        particle.style.animationDelay = (Math.random() * 15) + 's';
        container.appendChild(particle);
    }
}

// ─── Event Listeners ─────────────────────────────────────────
function bindEvents() {
    // Search
    searchBtn.addEventListener('click', performSearch);
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') performSearch();
    });

    // Quick tags
    document.querySelectorAll('.quick-tag').forEach(tag => {
        tag.addEventListener('click', () => {
            searchInput.value = tag.dataset.city;
            performSearch();
        });
    });

    // Trending cards
    document.querySelectorAll('.trending-card').forEach(card => {
        card.addEventListener('click', () => {
            searchInput.value = card.dataset.city;
            performSearch();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // Budget tabs
    document.getElementById('budget-tabs').addEventListener('click', (e) => {
        if (e.target.classList.contains('budget-tab')) {
            document.querySelectorAll('.budget-tab').forEach(t => t.classList.remove('active'));
            e.target.classList.add('active');
            currentBudget = e.target.dataset.budget;
            if (currentData) renderCosts(currentData.costs);
        }
    });

    // Navbar scroll
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 50);
        
        // Hide scroll indicator
        const indicator = document.getElementById('scroll-indicator');
        if (indicator) indicator.style.opacity = window.scrollY > 100 ? 0 : 1;
    });
}

// ═══════════════════════════════════════════════════════════════
// SEARCH
// ═══════════════════════════════════════════════════════════════

async function performSearch() {
    const place = searchInput.value.trim();
    if (!place) {
        showToast('Please enter a city name to explore');
        searchInput.focus();
        return;
    }

    // Loading state
    searchBtn.classList.add('loading');
    searchBtn.disabled = true;

    try {
        const response = await fetch(`api.php?action=search&place=${encodeURIComponent(place)}`, { credentials: 'same-origin' });
        const data = await response.json();

        if (data.error) {
            showToast(data.error);
            return;
        }

        currentData = data;
        renderResults(data);

    } catch (err) {
        showToast('Something went wrong. Make sure the PHP server is running.');
        console.error(err);
    } finally {
        searchBtn.classList.remove('loading');
        searchBtn.disabled = false;
    }
}

// ═══════════════════════════════════════════════════════════════
// RENDER RESULTS
// ═══════════════════════════════════════════════════════════════

function renderResults(data) {
    // Show results section
    resultsSection.style.display = 'block';

    // Update exchange rate for this destination
    if (data.costs && data.costs.exchange_rate_to_inr) {
        currentExchangeRate = data.costs.exchange_rate_to_inr;
    } else {
        currentExchangeRate = 84.5;
    }
    currentCurrency = (data.costs && data.costs.currency) ? data.costs.currency : 'USD';

    // Scroll to results
    setTimeout(() => {
        resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);

    renderPlaceHeader(data.place);
    renderCurrentWeather(data.current_weather);
    renderForecast(data.forecast);
    renderCosts(data.costs);
    renderHighlights(data.costs);
    renderTravelInfo(data.costs);
    renderTouristPlaces(data.tourist_places);

    // Animate sections in
    animateSections();
}

// ─── Place Header ────────────────────────────────────────────
function renderPlaceHeader(place) {
    document.getElementById('place-name').textContent = place.name;
    
    let location = '';
    if (place.state) location += place.state + ', ';
    location += place.country;
    document.getElementById('place-country').textContent = location;

    document.getElementById('place-coords').textContent = 
        `📍 ${place.lat.toFixed(2)}°N, ${place.lon.toFixed(2)}°E`;
}

// ─── Current Weather ─────────────────────────────────────────
function renderCurrentWeather(weather) {
    const container = document.getElementById('weather-current');
    container.innerHTML = `
        <div class="weather-main">
            <div class="weather-icon-wrap">
                <img src="https://openweathermap.org/img/wn/${weather.icon}@2x.png" 
                     alt="${weather.description}">
            </div>
            <div>
                <div class="weather-temp">
                    ${weather.temp}°<span class="unit">C</span>
                </div>
                <div class="weather-desc">${weather.description}</div>
                <div class="weather-feels">Feels like ${weather.feels_like}°C</div>
            </div>
        </div>
        <div class="weather-details">
            <div class="weather-detail">
                <div class="weather-detail-label">💧 Humidity</div>
                <div class="weather-detail-value">${weather.humidity}%</div>
            </div>
            <div class="weather-detail">
                <div class="weather-detail-label">💨 Wind</div>
                <div class="weather-detail-value">${weather.wind_speed} km/h</div>
            </div>
            <div class="weather-detail">
                <div class="weather-detail-label">🌅 Sunrise</div>
                <div class="weather-detail-value">${weather.sunrise}</div>
            </div>
            <div class="weather-detail">
                <div class="weather-detail-label">🌇 Sunset</div>
                <div class="weather-detail-value">${weather.sunset}</div>
            </div>
        </div>
    `;
}

// ─── 5-Day Forecast ──────────────────────────────────────────
function renderForecast(forecast) {
    const grid = document.getElementById('forecast-grid');
    grid.innerHTML = forecast.map(day => `
        <div class="forecast-card">
            <div class="forecast-day">${day.day_name.slice(0, 3)}</div>
            <div class="forecast-date">${formatDate(day.date)}</div>
            <img class="forecast-icon" 
                 src="https://openweathermap.org/img/wn/${day.icon}@2x.png" 
                 alt="${day.description}">
            <div class="forecast-temp">${day.temp}°C</div>
            <div class="forecast-desc">${day.description}</div>
            <div class="forecast-details">
                <span>💧${day.humidity}%</span>
                <span>💨${day.wind_speed}km/h</span>
            </div>
        </div>
    `).join('');
}

// ─── Cost Breakdown ──────────────────────────────────────────
function renderCosts(costs) {
    const container = document.getElementById('cost-breakdown');
    
    if (!costs || !costs.daily_budget) {
        container.innerHTML = '<p style="color: var(--text-muted)">Cost data not available for this destination.</p>';
        return;
    }

    const budget = costs.daily_budget[currentBudget];
    if (!budget) return;

    // Build data-source badges
    const sources = costs.data_sources || [];
    const rateSource = costs.rate_source || 'estimated';
    const rate       = costs.exchange_rate_to_inr;
    const rateText   = (rate && currentCurrency !== 'INR')
        ? ` &bull; 1 ${currentCurrency} = ${formatINR(Math.round(rate * 100) / 100)}`
        : '';

    let badgesHtml = '';
    if (sources.includes('Amadeus')) {
        badgesHtml += `<span class="data-source-badge amadeus-badge">🏨 Amadeus Live</span>`;
    }
    if (sources.includes('Teleport')) {
        badgesHtml += `<span class="data-source-badge teleport-badge">📊 Teleport</span>`;
    }
    if (sources.includes('static') || sources.length === 0) {
        badgesHtml += `<span class="data-source-badge static-badge">📋 Estimated</span>`;
    }
    if (rateText) {
        badgesHtml += `<span class="data-source-badge rate-badge">💱${rateText}</span>`;
    }

    document.getElementById('cost-currency').innerHTML = `(INR ₹) &nbsp;${badgesHtml}`;

    const categories = [
        { key: 'hotel',       icon: '🏨', label: 'Hotel',       note: 'per night', accent: 'var(--gradient-cool)' },
        { key: 'food',        icon: '🍽️', label: 'Food',        note: 'per day',   accent: 'var(--gradient-warm)' },
        { key: 'transport',   icon: '🚌', label: 'Transport',   note: 'per day',   accent: 'var(--gradient-fresh)' },
        { key: 'attractions', icon: '🎫', label: 'Attractions', note: 'per day',   accent: 'var(--gradient-primary)' },
    ];

    let html = '';

    categories.forEach(cat => {
        const nativeVal  = budget[cat.key];
        const inrVal     = toINR(nativeVal);
        const nativeNote = (currentCurrency !== 'INR')
            ? `<div class="cost-native-val">${nativeVal.toLocaleString()} ${currentCurrency}</div>`
            : '';
        html += `
            <div class="cost-card" style="--card-accent: ${cat.accent}">
                <div class="cost-card-icon">${cat.icon}</div>
                <div class="cost-card-label">${cat.label}</div>
                <div class="cost-card-value">${formatINR(inrVal)}</div>
                ${nativeNote}
                <div class="cost-card-note">${cat.note}</div>
            </div>
        `;
    });

    // Total card
    const totalNoteNative = (currentCurrency !== 'INR')
        ? `<div class="cost-native-val">${budget.total.toLocaleString()} ${currentCurrency}</div>`
        : '';
    html += `
        <div class="cost-card cost-total-card">
            <div class="cost-card-icon">💰</div>
            <div class="cost-card-label">Total Per Day</div>
            <div class="cost-card-value">${formatINR(toINR(budget.total))}</div>
            ${totalNoteNative}
            <div class="cost-card-note">estimated</div>
        </div>
    `;

    container.innerHTML = html;

    // Add bar chart below
    renderCostBars(budget, categories);
}

function renderCostBars(budget, categories) {
    const container = document.getElementById('cost-breakdown');
    const maxVal = budget.total;
    const colors = ['#6366f1', '#f59e0b', '#10b981', '#ec4899'];

    let barHtml = '<div class="cost-bar-container">';
    categories.forEach((cat, i) => {
        const pct = Math.round((budget[cat.key] / maxVal) * 100);
        barHtml += `
            <div class="cost-bar-row">
                <span class="cost-bar-label">${cat.icon} ${cat.label}</span>
                <div class="cost-bar-track">
                    <div class="cost-bar-fill" style="width: ${pct}%; background: ${colors[i]};">
                        ${formatINR(toINR(budget[cat.key]))}
                    </div>
                </div>
            </div>
        `;
    });
    barHtml += '</div>';
    container.innerHTML += barHtml;
}

// ─── Highlights ──────────────────────────────────────────────
function renderHighlights(costs) {
    const list = document.getElementById('highlights-list');
    
    if (!costs || !costs.highlights || costs.highlights.length === 0) {
        document.getElementById('info-section').style.display = 'none';
        return;
    }

    document.getElementById('info-section').style.display = 'block';

    list.innerHTML = costs.highlights.map((h, i) => `
        <li>
            <span class="highlight-number">${i + 1}</span>
            ${h}
        </li>
    `).join('');
}

// ─── Travel Info ─────────────────────────────────────────────
function renderTravelInfo(costs) {
    const container = document.getElementById('travel-info-content');

    if (!costs) {
        container.innerHTML = '<p style="color: var(--text-muted)">No travel info available.</p>';
        return;
    }

    let html = '';

    if (costs.best_season) {
        html += `
            <div class="travel-info-item">
                <span class="travel-info-label">🗓️ Best Season</span>
                <span class="travel-info-value">${costs.best_season}</span>
            </div>
        `;
    }

    if (costs.visa_info) {
        html += `
            <div class="travel-info-item">
                <span class="travel-info-label">🛂 Visa Info</span>
                <span class="travel-info-value">${costs.visa_info}</span>
            </div>
        `;
    }

    if (costs.currency) {
        html += `
            <div class="travel-info-item">
                <span class="travel-info-label">💱 Currency</span>
                <span class="travel-info-value">${costs.currency}</span>
            </div>
        `;
    }

    if (costs.country) {
        html += `
            <div class="travel-info-item">
                <span class="travel-info-label">🌍 Country</span>
                <span class="travel-info-value">${costs.country}</span>
            </div>
        `;
    }

    container.innerHTML = html || '<p style="color: var(--text-muted)">No additional info available.</p>';
}

// ─── Tourist Places (Geoapify + Wikipedia images) ───────────
function renderTouristPlaces(places) {
    const section = document.getElementById('places-section');
    const grid = document.getElementById('places-grid');

    if (!places || places.length === 0) {
        section.style.display = 'none';
        return;
    }

    section.style.display = 'block';

    grid.innerHTML = places.map((place, index) => {
        // Parse category kinds
        const kinds = place.kinds
            ? place.kinds.split(',')
                .filter(k => !['interesting_places', 'other', 'unclassified_objects'].includes(k))
                .slice(0, 3)
                .map(k => k.replace(/_/g, ' '))
            : [];

        // Links
        let linkHtml = '';
        if (place.wikipedia) {
            linkHtml += `<a href="${place.wikipedia}" target="_blank" rel="noopener" class="place-link wiki-link">📖 Wikipedia</a>`;
        }
        if (place.url) {
            linkHtml += `<a href="${place.url}" target="_blank" rel="noopener" class="place-link site-link">🌐 Website</a>`;
        }

        return `
            <div class="place-card">
                <div class="place-card-image no-img" id="place-img-${index}">
                    <div class="place-card-placeholder">📸</div>
                    <div class="place-card-overlay"></div>
                </div>
                <div class="place-card-body">
                    <h4 class="place-card-name">${place.name}</h4>
                    ${place.description ? `<p class="place-card-desc">${place.description}</p>` : ''}
                    ${kinds.length > 0 ? `<div class="place-tags">${kinds.map(k => `<span class="place-tag">${k}</span>`).join('')}</div>` : ''}
                    ${linkHtml ? `<div class="place-links">${linkHtml}</div>` : ''}
                </div>
            </div>
        `;
    }).join('');

    // Fetch Wikipedia images asynchronously
    places.forEach((place, index) => {
        fetchWikiImage(place.wiki_title || place.name, index, place.wiki_lang || 'en');
    });
}

/**
 * Fetch a thumbnail image from Wikipedia for a place
 */
async function fetchWikiImage(title, cardIndex, lang) {
    if (!title) return;

    const container = document.getElementById(`place-img-${cardIndex}`);
    if (!container) return;

    const cleanTitle = title.replace(/ /g, '_');

    // Try the original language Wikipedia first, then fall back to English
    const attempts = [
        `https://${lang}.wikipedia.org/api/rest_v1/page/summary/${encodeURIComponent(cleanTitle)}`,
    ];
    if (lang !== 'en') {
        // Also try searching by the same title on English Wikipedia
        attempts.push(`https://en.wikipedia.org/api/rest_v1/page/summary/${encodeURIComponent(cleanTitle)}`);
    }

    for (const url of attempts) {
        try {
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!resp.ok) continue;

            const data = await resp.json();
            const imgUrl = data.thumbnail?.source || data.originalimage?.source;

            if (imgUrl) {
                const img = document.createElement('img');
                img.src = imgUrl;
                img.alt = title;
                img.loading = 'lazy';
                img.style.opacity = '0';
                img.style.transition = 'opacity 0.5s ease';
                img.onload = () => {
                    container.classList.remove('no-img');
                    const placeholder = container.querySelector('.place-card-placeholder');
                    if (placeholder) placeholder.remove();
                    container.insertBefore(img, container.firstChild);
                    requestAnimationFrame(() => { img.style.opacity = '1'; });
                };
                img.onerror = () => {};
                return; // Stop on first successful image
            }
        } catch (e) {
            // Try next URL
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// UTILITIES
// ═══════════════════════════════════════════════════════════════

function formatDate(dateStr) {
    const d = new Date(dateStr);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${months[d.getMonth()]} ${d.getDate()}`;
}

function showToast(message) {
    toastMessage.textContent = message;
    errorToast.classList.add('show');
    setTimeout(() => errorToast.classList.remove('show'), 4000);
}

function animateSections() {
    const sections = resultsSection.querySelectorAll('.section-block, .place-header, .info-card');
    sections.forEach((section, i) => {
        section.classList.remove('animate-in');
        section.classList.remove(`stagger-${i + 1}`);
        
        // Force reflow
        void section.offsetWidth;
        
        section.classList.add('animate-in');
        section.classList.add(`stagger-${Math.min(i + 1, 5)}`);
    });
}
