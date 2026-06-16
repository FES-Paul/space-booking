import { useMemo, useState } from "react";
import { useBookingStore } from "@/store/bookingStore";
import type { PackageThemeMetaField, SelectionItem } from "@/types";
import {
  getPackageQuestionOthersLabel,
  isPackageQuestionOthersSelected,
} from "@/utils/packageQuestionAnswers";

type SelectedPackageItem = Extract<SelectionItem, { type: "package" }>;

type PackageQuestionEntry = {
  packageId: number;
  packageTitle: string;
  field: PackageThemeMetaField;
};

const answerKeyFor = (packageId: number, fieldKey: string) =>
  `pkg_${packageId}__${fieldKey}`;

export function Step4PackageQuestions() {
  const {
    selectedItems,
    packageQuestionAnswers,
    setPackageQuestionAnswer,
    nextStep,
    prevStep,
  } = useBookingStore();

  const [errors, setErrors] = useState<Record<string, string>>({});

  const entries = useMemo<PackageQuestionEntry[]>(() => {
    return selectedItems
      .filter((item): item is SelectedPackageItem => item.type === "package")
      .flatMap((pkg) => {
        const fields = Array.isArray(pkg.theme_meta_fields) ? pkg.theme_meta_fields : [];
        return fields.map((field) => ({
          packageId: Number(pkg.id),
          packageTitle: pkg.title,
          field,
        }));
      });
  }, [selectedItems]);

  const setValue = (
    field: PackageThemeMetaField,
    key: string,
    value: string | number | string[],
    othersText?: string,
  ) => {
    const othersSelected = isPackageQuestionOthersSelected(field, value);
    setPackageQuestionAnswer(key, value, othersSelected ? othersText : "");
    setErrors((prev) => {
      const next = { ...prev };
      delete next[key];
      if (!othersSelected || othersText !== undefined) {
        delete next[`${key}__others`];
      }
      return next;
    });
  };

  const validate = (): boolean => {
    const validationErrors: Record<string, string> = {};
    for (const entry of entries) {
      const key = answerKeyFor(entry.packageId, entry.field.key);
      const existing = packageQuestionAnswers[key];
      const value = existing?.value;
      const isEmpty =
        value === undefined ||
        value === null ||
        value === "" ||
        (Array.isArray(value) && value.length === 0);
      if (entry.field.required && isEmpty) {
        validationErrors[key] = "This field is required.";
      }

      const supportsOthers =
        !!entry.field.allow_others &&
        ["radio", "checkbox", "select"].includes(entry.field.type);
      const othersLabel = getPackageQuestionOthersLabel(entry.field);
      const othersSelected =
        supportsOthers && isPackageQuestionOthersSelected(entry.field, value);
      if (othersSelected && !String(existing?.others_text || "").trim()) {
        validationErrors[`${key}__others`] =
          `Please describe your "${othersLabel}" answer.`;
      }
    }
    setErrors(validationErrors);
    return Object.keys(validationErrors).length === 0;
  };

  const handleContinue = () => {
    if (!validate()) return;
    nextStep();
  };

  if (entries.length === 0) return null;

  return (
    <div className="sb-step sb-step-4">
      <h2 className="sb-step__title">Package Questions</h2>
      <p className="sb-step__subtitle">
        Please answer these package-specific questions before continuing.
      </p>

      <div className="sb-summary-grid">
        {entries.map((entry) => {
          const key = answerKeyFor(entry.packageId, entry.field.key);
          const answer = packageQuestionAnswers[key];
          const value = answer?.value;
          const type = entry.field.type;
          const options = Array.isArray(entry.field.options) ? entry.field.options : [];
          const othersEnabled =
            !!entry.field.allow_others &&
            ["radio", "checkbox", "select"].includes(type);
          const othersLabel = getPackageQuestionOthersLabel(entry.field);
          const optionPrices =
            entry.field.option_prices && typeof entry.field.option_prices === "object"
              ? entry.field.option_prices
              : {};
          const hasPricedOptions =
            !!entry.field.priced_options &&
            ["radio", "checkbox", "select"].includes(type);
          const renderOptionLabel = (opt: string) => {
            if (!hasPricedOptions) return opt;
            const lookupLabels =
              othersEnabled && opt === othersLabel
                ? [opt, "Others"]
                : [opt];
            let amount = 0;
            for (const lookupLabel of lookupLabels) {
              const matchedEntry = Object.entries(optionPrices).find(
                ([optionLabel]) =>
                  optionLabel.toLowerCase() === lookupLabel.toLowerCase(),
              );
              if (matchedEntry) {
                amount = Number(matchedEntry[1] ?? 0);
                break;
              }
            }
            if (amount <= 0) return opt;
            return `${opt} (+${window.sbConfig.symbol}${amount.toFixed(2)})`;
          };
          const othersSelected =
            othersEnabled && isPackageQuestionOthersSelected(entry.field, value);

          return (
            <div
              key={`${entry.packageId}-${entry.field.key}`}
              className="sb-summary-row"
              style={{ gridColumn: "1 / -1", display: "block" }}
            >
              <label style={{ display: "block", marginBottom: 6 }}>
                {entry.field.label}{" "}
                {entry.field.required ? <span style={{ color: "var(--sb-primary)" }}>*</span> : null}
                <span style={{ marginLeft: 8, color: "var(--sb-muted)", fontSize: 12 }}>
                  ({entry.packageTitle})
                </span>
              </label>

              {type === "text" && (
                <input
                  className="sb-input"
                  type="text"
                  value={String(value || "")}
                  onChange={(e) => setValue(entry.field, key, e.target.value)}
                />
              )}
              {type === "textarea" && (
                <textarea
                  className="sb-input"
                  value={String(value || "")}
                  onChange={(e) => setValue(entry.field, key, e.target.value)}
                  style={{ minHeight: 90 }}
                />
              )}
              {type === "number" && (
                <input
                  className="sb-input"
                  type="number"
                  value={String(value || "")}
                  onChange={(e) => setValue(entry.field, key, e.target.value)}
                />
              )}
              {type === "select" && (
                <select
                  className="sb-input"
                  value={String(value || "")}
                  onChange={(e) => setValue(entry.field, key, e.target.value)}
                >
                  <option value="">Select an option</option>
                  {options.map((opt) => (
                    <option key={opt} value={opt}>
                      {renderOptionLabel(opt)}
                    </option>
                  ))}
                  {othersEnabled && (
                    <option value={othersLabel}>
                      {hasPricedOptions ? renderOptionLabel(othersLabel) : othersLabel}
                    </option>
                  )}
                </select>
              )}
              {type === "radio" && (
                <div>
                  {options.map((opt) => (
                    <label key={opt} style={{ display: "block", marginBottom: 6 }}>
                      <input
                        type="radio"
                        name={key}
                        checked={value === opt}
                        onChange={() =>
                          setValue(entry.field, key, opt, answer?.others_text || "")
                        }
                      />{" "}
                      {renderOptionLabel(opt)}
                    </label>
                  ))}
                  {othersEnabled && (
                    <label style={{ display: "block", marginBottom: 6 }}>
                      <input
                        type="radio"
                        name={key}
                        checked={value === othersLabel}
                        onChange={() => setValue(entry.field, key, othersLabel)}
                      />{" "}
                      {hasPricedOptions ? renderOptionLabel(othersLabel) : othersLabel}
                    </label>
                  )}
                </div>
              )}
              {type === "checkbox" && (
                <div>
                  {options.map((opt) => {
                    const arr = Array.isArray(value) ? value : [];
                    const checked = arr.includes(opt);
                    return (
                      <label key={opt} style={{ display: "block", marginBottom: 6 }}>
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={(e) => {
                            const current = Array.isArray(value) ? value : [];
                            const next = e.target.checked
                              ? [...current, opt]
                              : current.filter((v) => v !== opt);
                            setValue(entry.field, key, next, answer?.others_text || "");
                          }}
                        />{" "}
                        {renderOptionLabel(opt)}
                      </label>
                    );
                  })}
                  {othersEnabled && (
                    <label style={{ display: "block", marginBottom: 6 }}>
                      <input
                        type="checkbox"
                        checked={Array.isArray(value) ? value.includes(othersLabel) : false}
                        onChange={(e) => {
                          const current = Array.isArray(value) ? value : [];
                          const next = e.target.checked
                            ? [...current, othersLabel]
                            : current.filter((v) => v !== othersLabel);
                          setValue(entry.field, key, next, answer?.others_text || "");
                        }}
                      />{" "}
                      {hasPricedOptions ? renderOptionLabel(othersLabel) : othersLabel}
                    </label>
                  )}
                </div>
              )}

              {othersSelected && (
                <div style={{ marginTop: 8 }}>
                  <label style={{ display: "block", marginBottom: 4 }}>
                    Please specify
                  </label>
                  <textarea
                    className="sb-input"
                    value={String(answer?.others_text || "")}
                    onChange={(e) =>
                      setValue(entry.field, key, answer?.value || "", e.target.value)
                    }
                    style={{ minHeight: 90 }}
                  />
                  {errors[`${key}__others`] && (
                    <span className="sb-error">{errors[`${key}__others`]}</span>
                  )}
                </div>
              )}
              {errors[key] && <span className="sb-error">{errors[key]}</span>}
            </div>
          );
        })}
      </div>

      <div className="sb-step__actions">
        <button className="sb-btn sb-btn--ghost" onClick={prevStep}>
          Back
        </button>
        <button className="sb-btn sb-btn--primary" onClick={handleContinue}>
          Continue
        </button>
      </div>
    </div>
  );
}
