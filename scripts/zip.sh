#!/bin/sh

cd opencart3
zip -r ../packages/opencart3/sellixpay.ocmod.zip *
cd ..

cd opencart4
zip -r ../packages/opencart4/sellixpay.ocmod.zip *
cd ..