import type { PackageThemeMetaField } from "@/types";

type PackageQuestionValue = string | number | string[] | undefined | null;

export const DEFAULT_PACKAGE_OTHERS_LABEL = "Others";

export const getPackageQuestionOthersLabel = (
  field?: Pick<PackageThemeMetaField, "others_label"> | null,
): string => {
  const label = String(field?.others_label ?? "").trim();
  return label || DEFAULT_PACKAGE_OTHERS_LABEL;
};

export const isPackageQuestionOthersSelected = (
  field: Pick<PackageThemeMetaField, "others_label"> | null | undefined,
  value: PackageQuestionValue,
): boolean => {
  const othersLabel = getPackageQuestionOthersLabel(field);
  return Array.isArray(value) ? value.includes(othersLabel) : value === othersLabel;
};

export const formatPackageQuestionAnswerSummary = (
  value: string | number | string[],
  othersText?: string,
): string => {
  const valueText = Array.isArray(value) ? value.join(", ") : String(value);
  const details = String(othersText ?? "").trim();
  return details ? `${valueText} | Details: ${details}` : valueText;
};
