import { Card } from "@heroui/react";
import { OperatorChip } from "./OperatorChip";

export type Profile = { name: string; operator: string; district: string; area: string };

/** "You are a [Robi] user from Mirpur, Dhaka." shown after registering or logging in. */
export function ResultCard({ profile, title, description, children }: {
  profile: Profile;
  title: string;
  description: string;
  children: React.ReactNode;
}) {
  return (
    <Card className="mx-auto mt-10 max-w-lg text-center">
      <Card.Header>
        <Card.Title className="text-2xl">{title}</Card.Title>
        <Card.Description>{description}</Card.Description>
      </Card.Header>
      <Card.Content className="text-lg">
        <p className="flex flex-wrap items-center justify-center gap-2">
          You are a <OperatorChip name={profile.operator} /> user from
          <strong>{profile.area}, {profile.district}</strong>.
        </p>
      </Card.Content>
      <Card.Footer className="justify-center gap-3">{children}</Card.Footer>
    </Card>
  );
}
