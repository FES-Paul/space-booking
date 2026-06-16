type BookingLabelItem = {
  id?: number;
  type?: string;
  title?: string;
};

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

export function formatPackagePreviewLabel(
  label: string,
  selectedPackageTitles: string[],
  startTime: string,
  endTime: string,
): string {
  const normalizedLabel = label.trim();
  const isSelectedPackage = selectedPackageTitles.some(
    (title) => title.trim() === normalizedLabel,
  );
  if (!isSelectedPackage) return label;

  const timeRangeLabel = formatBookingTimeRangeLabel(startTime, endTime);
  if (!timeRangeLabel) return label;

  return `${label} ${timeRangeLabel}`;
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
  if (item.type !== "sb_package") return title;

  const timeRangeLabel = formatBookingTimeRangeLabel(startTime, endTime);
  if (!timeRangeLabel) return title;

  return `${title} ${timeRangeLabel}`;
}
