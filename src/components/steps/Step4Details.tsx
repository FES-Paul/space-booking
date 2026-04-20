import React from "react";
import { useBookingStore } from "@/store/bookingStore";
import type { CustomField, CustomerValue } from "@/types";

export function Step4Details() {
  const {
    customerInfo,
    customerFields,
    fetchCustomerFields,
    validateCustomerInfo,
    nextStep,
    prevStep,
    setCustomerField,
  } = useBookingStore();

  React.useEffect(() => {
    fetchCustomerFields();
  }, []);

  const isValid = validateCustomerInfo();

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isValid) nextStep();
  };

  const handleFieldChange = (key: string, value: CustomerValue) => {
    setCustomerField(key, value);
  };

  const getFieldError = (field: CustomField) => {
    if (!field.required || customerInfo[field.key]) return "";
    if (field.type === "email") {
      const val = customerInfo[field.key] as string;
      if (val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val))
        return "Invalid email";
    }
    return "This field is required";
  };

  const isFieldInvalid = (field: CustomField) => {
    return !!getFieldError(field);
  };

  return (
    <div className="sb-step sb-step-4">
      <h2 className="sb-step__title">Your Details</h2>

      {customerFields.length === 0 ? (
        <div className="sb-loading">Loading customer fields...</div>
      ) : (
        <form className="sb-form" onSubmit={handleSubmit} noValidate>
          {customerFields.map((field) => (
            <div
              key={field.key}
              className={`sb-field ${isFieldInvalid(field) ? "sb-field--error" : ""}`}
            >
              <label className="sb-label" htmlFor={`sb-${field.key}`}>
                {field.label}{" "}
                {field.required && <span className="sb-required">*</span>}
              </label>
              {field.type === "textarea" ? (
                <textarea
                  id={`sb-${field.key}`}
                  className="sb-input sb-textarea"
                  rows={3}
                  placeholder={field.placeholder}
                  value={(customerInfo[field.key] as string) || ""}
                  onChange={(e) => handleFieldChange(field.key, e.target.value)}
                  required={field.required}
                />
              ) : field.type === "select" && field.options ? (
                <select
                  id={`sb-${field.key}`}
                  className="sb-input"
                  value={(customerInfo[field.key] as string) || ""}
                  onChange={(e) => handleFieldChange(field.key, e.target.value)}
                  required={field.required}
                >
                  <option value="">{field.placeholder || "Select..."}</option>
                  {field.options.map((opt) => (
                    <option key={opt} value={opt}>
                      {opt}
                    </option>
                  ))}
                </select>
              ) : (
                <input
                  id={`sb-${field.key}`}
                  type={field.type}
                  className="sb-input"
                  placeholder={field.placeholder}
                  value={(customerInfo[field.key] as string) || ""}
                  autoComplete={
                    field.type === "email"
                      ? "email"
                      : field.type === "tel"
                        ? "tel"
                        : "off"
                  }
                  onChange={(e) => handleFieldChange(field.key, e.target.value)}
                  required={field.required}
                />
              )}
              {isFieldInvalid(field) && (
                <p className="sb-error-message">{getFieldError(field)}</p>
              )}
              {field.key === "email" && (
                <p className="sb-hint">
                  We'll send your booking confirmation here.
                </p>
              )}
            </div>
          ))}
          <div className="sb-step__actions">
            <button
              type="button"
              className="sb-btn sb-btn--ghost"
              onClick={prevStep}
            >
              ← Back
            </button>
            <button
              type="submit"
              className="sb-btn sb-btn--primary"
              disabled={!isValid}
            >
              Review & Pay →
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
