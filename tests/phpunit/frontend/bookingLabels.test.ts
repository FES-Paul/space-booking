import { describe, expect, it } from "vitest";
import {
  formatConfirmationSelectedItemLabel,
  formatPackagePreviewLabel,
  formatTimeTo12Hour,
  shouldShowConfirmationSelectedItems,
} from "@/utils/bookingLabels";

describe("bookingLabels", () => {
  it("formats booking times in 12-hour display", () => {
    expect(formatTimeTo12Hour("09:30")).toBe("9:30 AM");
    expect(formatTimeTo12Hour("13:15")).toBe("1:15 PM");
  });

  it("adds a time suffix only to selected package preview labels", () => {
    expect(
      formatPackagePreviewLabel(
        "Birthday Package",
        ["Birthday Package"],
        "09:30",
        "11:30",
      ),
    ).toBe("Birthday Package (9:30 AM - 11:30 AM)");

    expect(
      formatPackagePreviewLabel(
        "Main Hall (Package Inclusion)",
        ["Birthday Package"],
        "09:30",
        "11:30",
      ),
    ).toBe("Main Hall (Package Inclusion)");
  });

  it("shows confirmation selected items for package-only bookings", () => {
    expect(
      shouldShowConfirmationSelectedItems([
        { id: 55, type: "sb_package", title: "Birthday Package" },
      ]),
    ).toBe(true);

    expect(
      shouldShowConfirmationSelectedItems([
        { id: 10, type: "sb_space", title: "Main Hall" },
      ]),
    ).toBe(false);
  });

  it("adds the booking time suffix only to confirmation package labels", () => {
    expect(
      formatConfirmationSelectedItemLabel(
        { id: 55, type: "sb_package", title: "Birthday Package" },
        "09:30",
        "11:30",
      ),
    ).toBe("Birthday Package (9:30 AM - 11:30 AM)");

    expect(
      formatConfirmationSelectedItemLabel(
        { id: 10, type: "sb_space", title: "Main Hall" },
        "09:30",
        "11:30",
      ),
    ).toBe("Main Hall");
  });
});
