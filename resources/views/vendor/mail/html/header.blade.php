@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiA0MiIgZmlsbD0ibm9uZSI+CiAgPGNpcmNsZSBjeD0iMTYiIGN5PSI0IiByPSIzLjgiIGZpbGw9IiMwNTk2NjkiLz4KICA8cGF0aCBmaWxsPSIjMDU5NjY5IiBkPSJNMSAxM2g3LjVMMTYgMzRsNy41LTIxSDMxTDE5LjUgNDBxLTMuNSAzLTcgMFoiLz4KPC9zdmc+Cg==" class="logo" alt="Voluntify" style="height: 40px; width: auto;">
<br>
{{ $slot }}
</a>
</td>
</tr>
