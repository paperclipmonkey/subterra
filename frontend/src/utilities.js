const convertFileToBase64 = (file) => {
  return new Promise((resolve, reject) => {
  const reader = new FileReader();
  reader.readAsDataURL(file);
  reader.onload = () => resolve({ data: reader.result, filename: file.name });
  reader.onerror = (error) => reject(error);
  });
};

export {convertFileToBase64};