"use client";

import { useState, type FormEvent } from "react";
import Link from "next/link";
import { Alert, Button, Card, FieldError, Form, Input, Label, TextField } from "@heroui/react";
import { api, ApiError } from "@/lib/api";
import { ResultCard, type Profile } from "@/components/ResultCard";

export default function LoginPage() {
  const [profile, setProfile] = useState<Profile | null>(null);
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const form = Object.fromEntries(new FormData(e.currentTarget));
    setLoading(true);
    setMessage("");
    try {
      setProfile(await api<Profile>("/api/login", { method: "POST", body: form }));
    } catch (err) {
      setMessage((err as ApiError).message);
    } finally {
      setLoading(false);
    }
  }

  if (profile) {
    return (
      <ResultCard profile={profile} title={`Welcome back, ${profile.name.split(" ")[0]}!`} description="You are logged in.">
        <Button variant="secondary" onPress={() => setProfile(null)}>Log out</Button>
      </ResultCard>
    );
  }

  return (
    <Card className="mx-auto mt-10 max-w-md">
      <Card.Header>
        <Card.Title>Log in</Card.Title>
        <Card.Description>Use the phone number and password you registered with.</Card.Description>
      </Card.Header>
      <Card.Content>
        {message && (
          <Alert status="danger" className="mb-4">
            <Alert.Indicator />
            <Alert.Content><Alert.Title>{message}</Alert.Title></Alert.Content>
          </Alert>
        )}
        <Form onSubmit={onSubmit} className="flex flex-col gap-4">
          <TextField isRequired name="phone" type="tel" autoComplete="tel" fullWidth>
            <Label>Mobile number</Label>
            <Input placeholder="018XXXXXXXX" />
            <FieldError />
          </TextField>
          <TextField isRequired name="password" type="password" autoComplete="current-password" fullWidth>
            <Label>Password</Label>
            <Input />
            <FieldError />
          </TextField>
          <Button type="submit" variant="primary" fullWidth isPending={loading}>Log in</Button>
        </Form>
        <p className="mt-4 text-center text-sm text-muted">
          No account yet? <Link href="/" className="text-accent hover:underline">Register</Link>
        </p>
      </Card.Content>
    </Card>
  );
}
