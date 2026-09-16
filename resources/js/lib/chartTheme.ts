/**
 * The colours the dashboard charts are drawn with.
 *
 * Both charts plot a single measure, so colour is not carrying identity here —
 * the axis already says which bar is which. That makes it one hue for every bar,
 * and the hue is blue rather than the brand yellow: at 1.4:1 against a white card
 * the yellow needed an outline drawn around every bar to be seen at all, and a
 * border drawn to rescue a fill is a fill that was never legible.
 *
 * Every value is a step from the documented chart palette, and each one was
 * measured against the card it actually renders on (white in light mode,
 * near-black in dark) rather than eyeballed:
 *
 *   series  4.42:1 light · 5.44:1 dark   (marks need 3:1)
 *   hover   6.63:1 both
 *   today   9.91:1 light · 9.38:1 dark
 *   labels  7.94:1 light · 11.05:1 dark  (text needs 4.5:1)
 *   axis    3.59:1 light · 5.51:1 dark
 *
 * Dark is its own set of steps chosen for the dark card, not the light set
 * dimmed: a colour that reads on white reads as mud on black.
 */
export interface ChartTheme {
    /** Bar fill. */
    series: string;
    /** The same hue a step further from the surface, for the bar under the pointer. */
    seriesHover: string;
    /** Further still, for today's bar: the one everybody looks for first. */
    seriesCurrent: string;
    /** Hairline grid, one shade off the card. */
    grid: string;
    /** Axis ticks. */
    axis: string;
    /** Values written onto the plot. Text wears ink, never the series colour. */
    label: string;
}

const lightTheme: ChartTheme = {
    series: '#2a78d6',
    seriesHover: '#1c5cab',
    seriesCurrent: '#104281',
    grid: '#e1e0d9',
    axis: '#898781',
    label: '#52514e',
};

const darkTheme: ChartTheme = {
    series: '#3987e5',
    seriesHover: '#5598e7',
    seriesCurrent: '#86b6ef',
    grid: '#2c2c2a',
    axis: '#898781',
    label: '#c3c2b7',
};

export function chartTheme(): ChartTheme {
    return document.documentElement.classList.contains('dark')
        ? darkTheme
        : lightTheme;
}
