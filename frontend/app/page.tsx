"use client";

import { useEffect, useState, type FormEvent } from "react";
import { Alert, Button, Card, FieldError, Form, Input, Label, ListBox, Select, TextField, type Key } from "@heroui/react";
import { api, ApiError } from "@/lib/api";
import Link from "next/link";
import { ResultCard, type Profile } from "@/components/ResultCard";

export default function RegisterPage() {
  const [districts, setDistricts] = useState<string[]>([]);
  const [district, setDistrict] = useState<Key | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<Profile | null>(null);

  useEffect(() => {
    api<{ districts: string[] }>("/api/districts")
      .then((r) => setDistricts(r.districts))
      .catch((e: ApiError) => setMessage(e.message));
  }, []);

  async function onSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const { confirm, ...form } = Object.fromEntries(new FormData(e.currentTarget)) as Record<string, string>;
    if (form.password !== confirm) {
      setErrors({ confirm: "Passwords do not match." });
      return;
    }
    setLoading(true);
    setErrors({});
    setMessage("");
    try {
      setResult(await api<Profile>("/api/register", { method: "POST", body: { ...form, district } }));
    } catch (err) {
      const apiErr = err as ApiError;
      setErrors(apiErr.errors);
      setMessage(Object.keys(apiErr.errors).length ? "" : apiErr.message);
    } finally {
      setLoading(false);
    }
  }

  if (result) {
    return (
      <ResultCard profile={result} title={`Thank you, ${result.name.split(" ")[0]}!`} description="Your registration is saved. You can now log in with your phone number and password.">
        <Link href="/login" className="font-medium text-accent hover:underline">Log in</Link>
        <Button variant="secondary" onPress={() => { setResult(null); setDistrict(null); }}>Register another number</Button>
      </ResultCard>
    );
  }

  return (
    <div className="mx-auto mt-6 grid max-w-5xl items-start gap-10 md:grid-cols-2">
      <section className="pt-4">
        <h1 className="text-4xl font-bold leading-tight">Find out your SIM operator and location.</h1>
        <p className="mt-4 text-muted">
          Register with your name, email, mobile number, area and a password. We detect your operator from the first digits
          of your number, and you can log in later with your number and password.
        </p>
      </section>

      <Card>
        <Card.Header>
          <Card.Title>Registration</Card.Title>
        </Card.Header>
        <Card.Content>
          {message && (
            <Alert status="danger" className="mb-4">
              <Alert.Indicator />
              <Alert.Content><Alert.Title>{message}</Alert.Title></Alert.Content>
            </Alert>
          )}
          <Form validationErrors={errors} onSubmit={onSubmit} className="flex flex-col gap-4">
            <TextField isRequired name="name" autoComplete="name" fullWidth>
              <Label>Full name</Label>
              <Input placeholder="Nahin" />
              <FieldError />
            </TextField>
            <TextField isRequired name="email" type="email" autoComplete="email" fullWidth>
              <Label>Email</Label>
              <Input placeholder="nahin@example.com" />
              <FieldError />
            </TextField>
            <TextField isRequired name="phone" type="tel" autoComplete="tel" fullWidth>
              <Label>Mobile number</Label>
              <Input placeholder="018XXXXXXXX" />
              <FieldError />
            </TextField>
            <TextField isRequired name="password" type="password" autoComplete="new-password" minLength={8} maxLength={72} fullWidth>
              <Label>Password</Label>
              <Input placeholder="At least 8 characters" />
              <FieldError />
            </TextField>
            <TextField isRequired name="confirm" type="password" autoComplete="new-password" fullWidth>
              <Label>Confirm password</Label>
              <Input />
              <FieldError />
            </TextField>
            <Select isRequired name="district" placeholder="Select district" fullWidth value={district} onChange={setDistrict}>
              <Label>District</Label>
              <Select.Trigger><Select.Value /><Select.Indicator /></Select.Trigger>
              <Select.Popover>
                <ListBox>
                  {districts.map((d) => (
                    <ListBox.Item key={d} id={d} textValue={d}>{d}<ListBox.ItemIndicator /></ListBox.Item>
                  ))}
                </ListBox>
              </Select.Popover>
              <FieldError />
            </Select>
            <TextField isRequired name="area" fullWidth>
              <Label>Area</Label>
              <Input placeholder="Mirpur" />
              <FieldError />
            </TextField>
            <Button type="submit" variant="primary" fullWidth isPending={loading}>Submit</Button>
          </Form>
        </Card.Content>
      </Card>
    </div>
  );
}
