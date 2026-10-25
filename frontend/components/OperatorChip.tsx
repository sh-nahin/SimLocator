import { Chip } from "@heroui/react";

const COLORS = { Robi: "danger", Grameenphone: "accent", Banglalink: "warning", Teletalk: "success" } as const;

export function OperatorChip({ name }: { name: string }) {
  return (
    <Chip color={COLORS[name as keyof typeof COLORS] ?? "default"} variant="soft">
      {name}
    </Chip>
  );
}
