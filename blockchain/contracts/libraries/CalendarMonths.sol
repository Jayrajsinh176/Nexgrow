// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

// UTC calendar months for day numbers (days since 1970-01-01).
//
// A month ID is year * 12 + (month - 1), e.g. October 2026 = 2026 * 12 + 9.
// Conversions use Howard Hinnant's civil-from-days algorithm, valid for every
// date from 1970 on.
library CalendarMonths {

    function monthOfDay(uint256 day) internal pure returns (uint256) {
        (uint256 y, uint256 m, ) = civilFromDays(day);

        return y * 12 + (m - 1);
    }

    // First day of the month.
    function startDay(uint256 monthId) internal pure returns (uint256) {
        return daysFromCivil(monthId / 12, (monthId % 12) + 1, 1);
    }

    // First day after the month.
    function endDay(uint256 monthId) internal pure returns (uint256) {
        return startDay(monthId + 1);
    }

    // The same day of the month `months` later; clamped to the last day when
    // the target month is shorter (e.g. Jan 31 + 1 month = Feb 28/29).
    function addMonths(uint256 day, uint256 months) internal pure returns (uint256) {
        (uint256 y, uint256 m, uint256 d) = civilFromDays(day);

        uint256 target = y * 12 + (m - 1) + months;
        uint256 first = startDay(target);
        uint256 length = endDay(target) - first;

        if (d > length) d = length;

        return first + d - 1;
    }

    function daysFromCivil(uint256 y, uint256 m, uint256 d) internal pure returns (uint256) {
        if (m <= 2) y -= 1;

        uint256 era = y / 400;
        uint256 yoe = y - era * 400;
        uint256 doy = (153 * (m > 2 ? m - 3 : m + 9) + 2) / 5 + d - 1;
        uint256 doe = yoe * 365 + yoe / 4 - yoe / 100 + doy;

        return era * 146097 + doe - 719468;
    }

    function civilFromDays(uint256 day)
        internal
        pure
        returns (uint256 y, uint256 m, uint256 d)
    {
        uint256 z = day + 719468;
        uint256 era = z / 146097;
        uint256 doe = z - era * 146097;
        uint256 yoe = (doe - doe / 1460 + doe / 36524 - doe / 146096) / 365;
        uint256 doy = doe - (365 * yoe + yoe / 4 - yoe / 100);
        uint256 mp = (5 * doy + 2) / 153;

        d = doy - (153 * mp + 2) / 5 + 1;
        m = mp < 10 ? mp + 3 : mp - 9;
        y = yoe + era * 400 + (m <= 2 ? 1 : 0);
    }
}
