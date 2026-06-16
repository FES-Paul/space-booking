import { describe, expect, it } from "vitest";
import {
  formatPackageQuestionAnswerSummary,
  getPackageQuestionOthersLabel,
  isPackageQuestionOthersSelected,
} from "@/utils/packageQuestionAnswers";

describe("packageQuestionAnswers", () => {
  it("defaults the others label when a package question does not provide one", () => {
    expect(getPackageQuestionOthersLabel()).toBe("Others");
    expect(getPackageQuestionOthersLabel({ others_label: "Custom Theme" })).toBe(
      "Custom Theme",
    );
  });

  it("detects custom others selections for single and multi-select answers", () => {
    const field = { others_label: "Custom Theme" };

    expect(isPackageQuestionOthersSelected(field, "Custom Theme")).toBe(true);
    expect(
      isPackageQuestionOthersSelected(field, ["Balloons", "Custom Theme"]),
    ).toBe(true);
    expect(isPackageQuestionOthersSelected(field, "Others")).toBe(false);
  });

  it("formats others answers with a generic details suffix", () => {
    expect(
      formatPackageQuestionAnswerSummary("Custom Theme", "Neon Glow"),
    ).toBe("Custom Theme | Details: Neon Glow");
  });
});
