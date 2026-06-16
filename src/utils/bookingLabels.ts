type BookingLabelItem = {
  id?: number;
  type?: string;
  title?: string;
};

const LABEL_TIME_RANGE_PATTERN = /\((\d{1,2}:\d{2})\s*[–-]\s*(\d{1,2}:\d{2})\)/;

export function formatTimeTo12Hour(timeStr: string): string {
  const value = timeStr.trim();
  const match = /^(\d{1,2}):(\d{2})/.exec(value);
  if (!match) return timeStr;

  let hour = Number(match[1]);
  const minute = match[2];
  if (Number.isNaN(hour)) return timeStr;

  const period = hour >= 12 ? "PM" : "AM";
  hour %= 12;
  if (hour === 0) hour = 12;

  return `${hour}:${minute} ${period}`;
}

export function formatBookingTimeRangeLabel(
  startTime: string,
  endTime: string,
): string {
  const start = startTime.trim();
  const end = endTime.trim();
  if (!start || !end) return "";

  return `(${formatTimeTo12Hour(start)} - ${formatTimeTo12Hour(end)})`;
}

function normalizeLabelTimeRange(label: string): string {
  return label.replace(
    LABEL_TIME_RANGE_PATTERN,
    (_match, startTime: string, endTime: string) =>
      formatBookingTimeRangeLabel(startTime, endTime),
  );
}

export function formatPreviewBreakdownLabel(
  label: string,
  selectedItemTitles: string[],
  startTime: string,
  endTime: string,
): string {
  const normalizedLabel = normalizeLabelTimeRange(label.trim());
  if (normalizedLabel !== label.trim()) {
    return normalizedLabel;
  }

  const isSelectedItem = selectedItemTitles.some(
    (title) => title.trim() === normalizedLabel,
  );
  if (!isSelectedItem) return normalizedLabel;

  const timeRangeLabel = formatBookingTimeRangeLabel(startTime, endTime);
  if (!timeRangeLabel) return normalizedLabel;

  return `${normalizedLabel} ${timeRangeLabel}`;
}

export function shouldShowConfirmationSelectedItems(
  items: BookingLabelItem[],
): boolean {
  return items.length > 1 || items.some((item) => item.type === "sb_package");
}

export function formatConfirmationSelectedItemLabel(
  item: BookingLabelItem,
  startTime: string,
  endTime: string,
): string {
  const title = item.title?.trim() || "";
  if (!title) return "";

  const timeRangeLabel = formatBookingTimeRangeLabel(startTime, endTime);
  if (!timeRangeLabel) return title;

  return `${title} ${timeRangeLabel}`;
}
