document.addEventListener('DOMContentLoaded', function () {
    // Get countdown format from WordPress options (passed via localized script)
    const countdownFormat = (typeof promoforge_vars !== 'undefined' && promoforge_vars.countdown_format) ? promoforge_vars.countdown_format : 'format1';

    function formatCountdown(days, hours, minutes, seconds, isUpcoming, format) {
        const prefix = isUpcoming ? 'Starts in: ' : 'Ends in: ';

        switch (format) {
            case 'format1':
                // "Ends in: 1d 2h 13m 18s"
                let display = prefix;
                if (days > 0) display += days + 'd ';
                if (hours > 0 || days > 0) display += hours + 'h ';
                if (minutes > 0 || hours > 0 || days > 0) display += minutes + 'm ';
                display += seconds + 's';
                return display.trim();

            case 'format2':
                // "1 Day 2 Hours 13 Minutes 18 Seconds"
                let parts = [];
                if (days > 0) parts.push(days + (days === 1 ? ' Day' : ' Days'));
                if (hours > 0) parts.push(hours + (hours === 1 ? ' Hour' : ' Hours'));
                if (minutes > 0) parts.push(minutes + (minutes === 1 ? ' Minute' : ' Minutes'));
                if (seconds > 0 || parts.length === 0) parts.push(seconds + (seconds === 1 ? ' Second' : ' Seconds'));
                return prefix + parts.join(' ');

            case 'format3':
                // "01:02:13:18" (DD:HH:MM:SS)
                return prefix + String(days).padStart(2, '0') + ':' +
                    String(hours).padStart(2, '0') + ':' +
                    String(minutes).padStart(2, '0') + ':' +
                    String(seconds).padStart(2, '0');

            default:
                return prefix + days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
        }
    }

    function updateAllCountdowns() {
        const countdownEls = document.querySelectorAll('.flash-offer-countdown-timer');
        if (!countdownEls.length) return;

        const now = new Date().getTime();

        countdownEls.forEach(function (countdownEl) {
            const isUpcoming = countdownEl.classList.contains('upcoming-offer');
            const timeKey = isUpcoming ? 'start' : 'end';

            if (!countdownEl.dataset[timeKey]) return;

            const targetTime = new Date(countdownEl.dataset[timeKey]).getTime();
            const distance = targetTime - now;

            if (distance <= 0) {
                // Optional: reload logic, but careful not to loop-reload if multiple expire
                // For now, simpler to just say "Expired" or hide
                countdownEl.textContent = 'Expired';
                // location.reload(); // Be careful with this on pages with many products
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            const formattedTime = formatCountdown(days, hours, minutes, seconds, isUpcoming, countdownFormat);
            countdownEl.textContent = formattedTime;
        });
    }

    // Run immediately
    updateAllCountdowns();

    // Run every second
    setInterval(updateAllCountdowns, 1000);
});