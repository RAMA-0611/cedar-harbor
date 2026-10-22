type FeedbackMessageProps = {
  kind: "success" | "error";
  children: string;
};

export function FeedbackMessage({ kind, children }: FeedbackMessageProps) {
  const isError = kind === "error";
  return (
    <p
      role={isError ? "alert" : "status"}
      className={`rounded-lg border px-3 py-2 text-sm ${
        isError
          ? "border-danger/40 bg-danger/10 text-danger"
          : "border-ok/40 bg-ok/10 text-ok"
      }`}
    >
      {children}
    </p>
  );
}
