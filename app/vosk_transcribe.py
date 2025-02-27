#!/usr/bin/env python3
import sys, json, wave
from vosk import Model, KaldiRecognizer

if len(sys.argv) < 2:
    sys.exit("Usage: {} <audio.wav>".format(sys.argv[0]))

wav_file = sys.argv[1]
wf = wave.open(wav_file, "rb")
if wf.getnchannels() != 1 or wf.getsampwidth() != 2 or wf.getframerate() != 16000:
    sys.exit("Аудіофайл має бути WAV, mono, 16kHz.")

# Завантажте модель, яка підтримує українську та англійську (шлях до моделі)
model = Model("/Users/vlad/PhpstormProjects/TGBOT-V1/vosk_model")
rec = KaldiRecognizer(model, wf.getframerate())

results = []
while True:
    data = wf.readframes(4000)
    if len(data) == 0:
        break
    if rec.AcceptWaveform(data):
        results.append(json.loads(rec.Result()).get("text", ""))
results.append(json.loads(rec.FinalResult()).get("text", ""))

print(" ".join(results))
