# About Subtitle Converter
It is used to converts subtitle files from one format, to other: .srt, .stl...

# Inroduction
Best way to learn is to see example. Let's convert .srt file to .stl:

```
SubtitleConverter::loadFile('movie.srt')->convertTo('stl')->toFile('movie.stl');
```


